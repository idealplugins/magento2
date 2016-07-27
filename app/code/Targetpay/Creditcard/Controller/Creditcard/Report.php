<?php
namespace Targetpay\Creditcard\Controller\Creditcard;

use Targetpay\Core\TargetPayCore;

/**
 * Targetpay Creditcard Report Controller
 *
 * @method POST
 */
class Report extends \Magento\Framework\App\Action\Action
{

    /**
     * @var \Targetpay\Creditcard\Model\Creditcard
     */
    protected $creditcard;
    /**
     * @var \Magento\Sales\Model\Order
     */
    protected $order;
    /**
     * @var \Magento\Framework\App\ResourceConnection
     */
    protected $resoureConnection;
    /**
     * @var \Magento\Backend\Model\Locale\Resolver
     */
    protected $localeResolver;
    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $scopeConfig;
    /**
     * @var \Magento\Framework\DB\Transaction
     */
    protected $transaction;

    /**
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Magento\Framework\App\ResourceConnection $resourceConnection
     * @param \Magento\Backend\Model\Locale\Resolver $localeResolver
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Framework\DB\Transaction $transaction
     * @param \Magento\Sales\Model\Order $order
     * @param \Targetpay\Creditcard\Model\Creditcard $creditcard
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Framework\App\ResourceConnection $resourceConnection,
        \Magento\Backend\Model\Locale\Resolver $localeResolver,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Framework\DB\Transaction $transaction,
        \Magento\Sales\Model\Order $order,
        \Targetpay\Creditcard\Model\Creditcard $creditcard
    ) {
        $this->resoureConnection = $resourceConnection;
        $this->localeResolver = $localeResolver;
        $this->scopeConfig = $scopeConfig;
        $this->transaction = $transaction;
        $this->order = $order;
        $this->creditcard = $creditcard;
        parent::__construct($context);
    }

    /**
     * When a customer return to website from Targetpay Creditcard gateway after a payment is marked as successful.
     * This is an asynchronous call.
     *
     * @return void|string
     */
    public function execute()
    {
        $orderId = (int)$this->getRequest()->getParam('order_id');
        $txId = (string)$this->getRequest()->getParam('trxid', null);
        if (!isset($txId)) {
            die("invalid callback, txid missing");
        }

        $db = $this->resoureConnection->getConnection();
        $sql = "SELECT `paid` FROM `targetpay` 
                WHERE `order_id` = " . $db->quote($orderId) . " 
                AND `targetpay_txid` = " . $db->quote($txId) . " 
                AND method=" . $db->quote($this->creditcard->getMethodType());
        
        $result = $db->fetchAll($sql);
        if (!count($result)) {
            die('transaction not found');
        }

        $alreadyPaid = ((!empty($result[0]['paid'])) ? true : false);

        if ($alreadyPaid) {
            die('callback already processed');
        }

        $language = ($this->localeResolver->getLocale() == 'nl_NL') ? 'nl' : 'en';
        $targetPay = new TargetPayCore(
            $this->creditcard->getMethodType(),
            $this->scopeConfig->getValue('payment/creditcard/rtlo'),
            $this->creditcard->getAppId(),
            $language,
            false
        );
        $targetPay->checkPayment($txId);

        $paymentStatus = (bool) $targetPay->getPaidStatus();
        $testMode = (bool) $this->scopeConfig->getValue('payment/creditcard/testmode');
        if ($testMode) {
            $paymentStatus = true; // Always OK if in testmode
            echo "Testmode... ";
        }

        if ($paymentStatus) {
            $sql = "UPDATE `targetpay` 
                SET `paid` = now() WHERE `order_id` = '" . $orderId . "'
                AND method='" . $this->creditcard->getMethodType() . "'
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
                echo "Paid... ";
            } else {
                echo "Already completed, skipped... ";
            }
        } else {
            echo "Not paid " . $targetPay->getErrorMessage() . "... ";
        }

        echo "(Magento, 15-06-2016)";
        die();
    }
}
