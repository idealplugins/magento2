<?php
namespace Targetpay\Mrcash\Controller\Mrcash;

use Targetpay\Core\TargetPayCore;

/**
 * Targetpay Mrcash Report Controller
 *
 * @method POST
 */
class Report extends \Magento\Framework\App\Action\Action
{
    /**
     * @var \Targetpay\Mrcash\Model\Mrcash
     */
    private $mrcash;

    /**
     * @var \Magento\Sales\Model\Order
     */
    private $order;

    /**
     * @var \Magento\Framework\App\ResourceConnection
     */
    private $resoureConnection;

    /**
     * @var \Magento\Backend\Model\Locale\Resolver
     */
    private $localeResolver;

    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @var \Magento\Framework\DB\Transaction
     */
    private $transaction;

    /**
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Magento\Framework\App\ResourceConnection $resourceConnection
     * @param \Magento\Backend\Model\Locale\Resolver $localeResolver
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Framework\DB\Transaction $transaction
     * @param \Magento\Sales\Model\Order $order
     * @param \Targetpay\Mrcash\Model\Mrcash $mrcash
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Framework\App\ResourceConnection $resourceConnection,
        \Magento\Backend\Model\Locale\Resolver $localeResolver,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Framework\DB\Transaction $transaction,
        \Magento\Sales\Model\Order $order,
        \Targetpay\Mrcash\Model\Mrcash $mrcash
    ) {
        $this->resoureConnection = $resourceConnection;
        $this->localeResolver = $localeResolver;
        $this->scopeConfig = $scopeConfig;
        $this->transaction = $transaction;
        $this->order = $order;
        $this->mrcash = $mrcash;
        parent::__construct($context);
    }

    /**
     * When a customer return to website from Targetpay Mrcash gateway after a payment is marked as successful.
     * This is an asynchronous call.
     *
     * @return void|string
     */
    public function execute()
    {
        $orderId = (int)$this->getRequest()->getParam('order_id');
        $txId = (string)$this->getRequest()->getParam('trxid', null);
        if (!isset($txId)) {
            $this->getResponse()->setBody("invalid callback, txid missing");
            return;
        }

        $db = $this->resoureConnection->getConnection();
        $sql = "SELECT `paid` FROM `targetpay` 
                WHERE `order_id` = " . $db->quote($orderId) . " 
                AND `targetpay_txid` = " . $db->quote($txId) . " 
                AND method=" . $db->quote($this->mrcash->getMethodType());
        
        $result = $db->fetchAll($sql);
        if (!count($result)) {
            $this->getResponse()->setBody('transaction not found');
            return;
        }

        $alreadyPaid = ((!empty($result[0]['paid'])) ? true : false);

        if ($alreadyPaid) {
            $this->getResponse()->setBody('callback already processed');
            return;
        }

        $language = ($this->localeResolver->getLocale() == 'nl_NL') ? 'nl' : 'en';
        $targetPay = new TargetPayCore(
            $this->mrcash->getMethodType(),
            $this->scopeConfig->getValue('payment/mrcash/rtlo'),
            $this->mrcash->getAppId(),
            $language,
            false
        );
        $targetPay->checkPayment($txId);

        $paymentStatus = (bool) $targetPay->getPaidStatus();
        $testMode = (bool) $this->scopeConfig->getValue('payment/mrcash/testmode');
        if ($testMode) {
            $paymentStatus = true; // Always OK if in testmode
            $this->getResponse()->setBody("Testmode... ");
        }

        if ($paymentStatus) {
            $sql = "UPDATE `targetpay` 
                SET `paid` = now() WHERE `order_id` = '" . $orderId . "'
                AND method='" . $this->mrcash->getMethodType() . "'
                AND `targetpay_txid` = '" . $txId . "'";
            $db->query($sql);

            $currentOrder = $this->order->loadByIncrementId($orderId);
            if ($currentOrder->getState() !=  \Magento\Sales\Model\Order::STATE_PROCESSING) {
                $invoice = $currentOrder->prepareInvoice();
                $invoice->register()->capture();
                $this->transaction->addObject($invoice)->addObject($invoice->getOrder())->save();

                $currentOrder->setStatus('Processing');
                $currentOrder->setIsInProcess(true);
                $currentOrder->setState(
                    \Magento\Sales\Model\Order::STATE_PROCESSING,
                    true,
                    'Invoice #' . $invoice->getIncrementId() . ' created.'
                );

                $invoice->sendEmail();

                $currentOrder->sendNewOrderEmail();
                $currentOrder->setEmailSent(true);
                $currentOrder->save();
                $this->getResponse()->setBody("Paid... ");
            } else {
                $this->getResponse()->setBody("Already completed, skipped... ");
            }
        } else {
            $this->getResponse()->setBody("Not paid " . $targetPay->getErrorMessage() . "... ");
        }

        $this->getResponse()->setBody("(Magento, 15-06-2016)");
        return;
    }
}
