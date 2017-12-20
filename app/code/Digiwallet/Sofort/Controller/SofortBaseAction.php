<?php
namespace Digiwallet\Sofort\Controller;

use Digiwallet\Core\TargetPayCore;

/**
 * Digiwallet Sofort Report Controller
 *
 * @method POST
 */
class SofortBaseAction extends \Magento\Framework\App\Action\Action
{
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
     * @var \Magento\Framework\Mail\Template\TransportBuilder
     */
    protected $transportBuilder;

    /**
     * @var \Magento\Sales\Model\Order
     */
    protected $order;

    /**
     * @var \Digiwallet\Sofort\Model\Sofort
     */
    protected $sofort;

    /**
     * @var \Magento\Sales\Api\TransactionRepositoryInterface
     */
    protected $transactionRepository;
    
    /**
     * @var \Magento\Sales\Model\Order\Payment\Transaction\BuilderInterface
     */
    protected $transactionBuilder;
    /**
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Magento\Framework\App\ResourceConnection $resourceConnection
     * @param \Magento\Backend\Model\Locale\Resolver $localeResolver
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Framework\DB\Transaction $transaction
     * @param \Magento\Framework\Mail\Template\TransportBuilder $transportBuilder
     * @param \Magento\Sales\Model\Order $order
     * @param \Digiwallet\Sofort\Model\Sofort $sofort
     * @param \Magento\Sales\Api\TransactionRepositoryInterface $transactionRepository
     * @param \Magento\Sales\Model\Order\Payment\Transaction\BuilderInterface $transactionBuilder
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Framework\App\ResourceConnection $resourceConnection,
        \Magento\Backend\Model\Locale\Resolver $localeResolver,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Framework\DB\Transaction $transaction,
        \Magento\Framework\Mail\Template\TransportBuilder $transportBuilder,
        \Magento\Sales\Model\Order $order,
        \Digiwallet\Sofort\Model\Sofort $sofort,
        \Magento\Sales\Api\TransactionRepositoryInterface $transactionRepository,
        \Magento\Sales\Model\Order\Payment\Transaction\BuilderInterface $transactionBuilder
    ) {
        parent::__construct($context);
        $this->resoureConnection = $resourceConnection;
        $this->localeResolver = $localeResolver;
        $this->scopeConfig = $scopeConfig;
        $this->transaction = $transaction;
        $this->transportBuilder = $transportBuilder;
        $this->order = $order;
        $this->sofort = $sofort;
        $this->transactionRepository = $transactionRepository;
        $this->transactionBuilder = $transactionBuilder;
    }
    /**
     * Need Override and do nothing
     * {@inheritDoc}
     * @see \Magento\Framework\App\ActionInterface::execute()
     */
    public function execute()
    {
        // Do nothings
        return;
    }
    /**
     * When a customer return to website from Digiwallet Sofort gateway after a payment is marked as successful.
     * This is an asynchronous call.
     *
     * @return void|string
     */
    public function checkTargetPayResult()
    {
        $orderId = (int)$this->getRequest()->getParam('order_id');
        $txId = (string)$this->getRequest()->getParam('trxid', null);
        if (!isset($txId)) {
            $this->getResponse()->setBody("Invalid callback, trxid missing");
            return false;
        }

        $db = $this->resoureConnection->getConnection();
        $tableName   = $this->resoureConnection->getTableName('digiwallet_transaction');
        $sql = "SELECT `paid` FROM ".$tableName." 
                WHERE `order_id` = " . $db->quote($orderId) . " 
                AND `digi_txid` = " . $db->quote($txId) . " 
                AND method=" . $db->quote($this->sofort->getMethodType());
        
        $result = $db->fetchAll($sql);
        if (!count($result)) {
            $this->getResponse()->setBody('Transaction not found');
            return false;
        }

        $alreadyPaid = ((!empty($result[0]['paid'])) ? true : false);

        if ($alreadyPaid) {
            $this->getResponse()->setBody('Callback already processed');
            return true;
        }

        $language = ($this->localeResolver->getLocale() == 'nl_NL') ? 'nl' : 'en';
        $testMode = (bool) $this->scopeConfig->getValue('payment/sofort/testmode');
        $digiCore = new TargetPayCore(
            $this->sofort->getMethodType(),
            $this->scopeConfig->getValue('payment/sofort/rtlo'),
            $language,
            $testMode
        );
        $digiCore->checkPayment($txId);

        $paymentStatus = (bool) $digiCore->getPaidStatus();
        if ($testMode) {
            $paymentStatus = true; // Always OK if in testmode
            $this->getResponse()->setBody("Testmode... ");
        }

        // Load current Order
        $currentOrder = $this->order->loadByIncrementId($orderId);
        if ($paymentStatus) {
            $sql = "UPDATE ".$tableName." 
                SET `paid` = now() WHERE `order_id` = '" . $orderId . "'
                AND method='" . $this->sofort->getMethodType() . "'
                AND `digi_txid` = '" . $txId . "'";
            $db->query($sql);

            if ($currentOrder->getState() != \Magento\Sales\Model\Order::STATE_PROCESSING)
            {
                $payment_message = __('OrderId: %1 - Digiwallet transactionId: %2 - Total price: %3', 
                    $orderId, 
                    $txId, 
                    $currentOrder->getBaseCurrency()->formatTxt($currentOrder->getGrandTotal())
                    );
                // Add transaction for refunable
                $payment = $currentOrder->getPayment();
                $payment->setLastTransId($txId);
                $payment->setTransactionId($txId);
                $orderTransactionId = $payment->getTransactionId();
                $transaction = $this->transactionBuilder->setPayment($payment)
                                ->setOrder($currentOrder)
                                ->setTransactionId($payment->getTransactionId())
                                ->build(\Magento\Sales\Model\Order\Payment\Transaction::TYPE_ORDER);
                $payment->addTransactionCommentsToOrder($transaction, $payment_message);
                $payment->setParentTransactionId($transaction->getTransactionId());
                $payment->save();
                // Invoice
                $invoice = $currentOrder->prepareInvoice();
                $invoice->register()->capture();
                $this->transaction->addObject($invoice)->addObject($invoice->getOrder())->save();
                $invoice->setTransactionId($payment->getTransactionId());
                // Save order
                $currentOrder->setIsInProcess(true);
                $currentOrder->setState(\Magento\Sales\Model\Order::STATE_PROCESSING);
                $currentOrder->setStatus(\Magento\Sales\Model\Order::STATE_PROCESSING);
                $currentOrder->addStatusToHistory(\Magento\Sales\Model\Order::STATE_PROCESSING, $payment_message, true);
                $invoice->setSendEmail(true);
                $currentOrder->save();
                $this->getResponse()->setBody("Paid... ");
            } 
            else 
            {
                $this->getResponse()->setBody("Already completed, skipped... ");
            }
            return true;
        }
        else 
        {
            /* Send failure payment email to customer */
            $storeScope = \Magento\Store\Model\ScopeInterface::SCOPE_STORE;
            $transport = $this->transportBuilder
                ->setTemplateIdentifier(
                    $this->scopeConfig->getValue('payment/sofort/email_template/failure'),
                    $storeScope
                )
                ->setTemplateOptions([
                    'area' => \Magento\Framework\App\Area::AREA_FRONTEND,
                    'store' => $currentOrder->getStoreId(),
                ])
                ->setTemplateVars(['order' => $currentOrder])
                ->setFrom([
                    'name' => $this->scopeConfig->getValue('trans_email/ident_support/name', $storeScope),
                    'email' => $this->scopeConfig->getValue('trans_email/ident_support/email', $storeScope)
                ])
                ->addTo($currentOrder->getCustomerEmail())
                ->getTransport();

            $transport->sendMessage();
            $this->getResponse()->setBody("Not paid " . $digiCore->getErrorMessage() . "... ");
        }
        // Not paid
        return false;
    }
}
