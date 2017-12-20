<?php
namespace Digiwallet\DPaypal\Controller;

use Digiwallet\Core\TargetPayCore;
/**
 * Digiwallet DPaypal Report Controller
 *
 * @method POST
 */
class DPaypalBaseAction extends \Magento\Framework\App\Action\Action
{

    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $checkoutSession;
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
     * @var \Digiwallet\DPaypal\Model\DPaypal
     */
    protected $dpaypal;
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
     * @param \Digiwallet\DPaypal\Model\DPaypal $dpaypal
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
        \Digiwallet\DPaypal\Model\DPaypal $dpaypal,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Sales\Api\TransactionRepositoryInterface $transactionRepository,
        \Magento\Sales\Model\Order\Payment\Transaction\BuilderInterface $transactionBuilder
        ) 
    {
            parent::__construct($context);
            $this->resoureConnection = $resourceConnection;
            $this->checkoutSession = $checkoutSession;
            $this->localeResolver = $localeResolver;
            $this->scopeConfig = $scopeConfig;
            $this->transaction = $transaction;
            $this->transportBuilder = $transportBuilder;
            $this->order = $order;
            $this->dpaypal = $dpaypal;
            $this->transactionRepository = $transactionRepository;
            $this->transactionBuilder = $transactionBuilder;
    }

    /***
     * Use to check order from target pay
     * @return boolean
     */
    public function checkTargetPayResult($txId, $orderId)
    {
        $language = ($this->localeResolver->getLocale() == 'nl_NL') ? 'nl' : 'en';
        $testMode = (bool) $this->scopeConfig->getValue('payment/dpaypal/testmode');
        $digiCore = new TargetPayCore(
            $this->dpaypal->getMethodType(),
            $this->scopeConfig->getValue('payment/dpaypal/rtlo'),
            $language,
            $testMode
            );
        $digiCore->checkPayment($txId);

        $paymentStatus = (bool) $digiCore->getPaidStatus();
        if ($testMode) {
            $paymentStatus = true; // Always OK if in testmode
            $this->getResponse()->setBody("Testmode... ");
        }
        /* @var \Magento\Sales\Model\Order $currentOrder */
        $currentOrder = $this->order->loadByIncrementId($orderId);
        if ($paymentStatus) {
            $db = $this->resoureConnection->getConnection();
            $tableName   = $this->resoureConnection->getTableName('digiwallet_transaction');
            $sql = "UPDATE ".$tableName."
                SET `paid` = now() WHERE `order_id` = '" . $orderId . "'
                AND method='" . $this->dpaypal->getMethodType() . "'
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
        } else {
            $this->getResponse()->setBody("Payment Error: " . $digiCore->getErrorMessage());
            /* Send failure payment email to customer */
            $storeScope = \Magento\Store\Model\ScopeInterface::SCOPE_STORE;
            $transport = $this->transportBuilder
            ->setTemplateIdentifier(
                $this->scopeConfig->getValue('payment/dpaypal/email_template/failure'),
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
        }
        return false;
    }
    /**
     * Empty action
     *
     * @return void|string
     */
    public function execute()
    {
        return;
    }
}
