<?php
namespace Digiwallet\Bankwire\Controller;

use Digiwallet\Core\TargetPayCore;

/**
 * Digiwallet Bankwire Report Controller
 *
 * @method POST
 */
class BankwireBaseAction extends \Magento\Framework\App\Action\Action
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
     * @var \Digiwallet\Bankwire\Model\Bankwire
     */
    protected $bankwire;

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
     * @param \Digiwallet\Bankwire\Model\Bankwire $bankwire
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
        \Digiwallet\Bankwire\Model\Bankwire $bankwire,
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
            $this->bankwire = $bankwire;
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
        $testMode = (bool) $this->scopeConfig->getValue('payment/bankwire/testmode');
        $digiCore = new TargetPayCore(
            $this->bankwire->getMethodType(),
            $this->scopeConfig->getValue('payment/bankwire/rtlo'),
            $language,
            $testMode
            );
        $checksum = md5($txId . $this->scopeConfig->getValue('payment/bankwire/rtlo') . $this->bankwire->getSalt());
        $digiCore->checkPayment($txId, ['checksum' => $checksum, 'once' => 0]);

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
                AND method='" . $this->bankwire->getMethodType() . "'
                AND `digi_txid` = '" . $txId . "'";
            $db->query($sql);
            if (!in_array($currentOrder->getState(), [
                    \Magento\Sales\Model\Order::STATE_PROCESSING,
                    \Magento\Sales\Model\Order::STATE_PAYMENT_REVIEW
                ])
            ) {
                // Invoice
                $invoice = $currentOrder->prepareInvoice();
                
                // Capture paid amount
                $paymentIsPartial = false;
                $consumber_info = $digiCore->getConsumerInfo();
                if (!empty($consumber_info) && $consumber_info['bw_paid_amount'] > 0) {
                    $invoice->setBaseGrandTotal($consumber_info['bw_paid_amount'] / 100);
                    $invoice->setGrandTotal($consumber_info['bw_paid_amount'] / 100);
                    if ($consumber_info['bw_paid_amount'] < $consumber_info['bw_due_amount']) {
                        $paymentIsPartial = true;
                    }
                }

                // Save invoice first because it does a setState on the order object
                $invoice->register()->capture();
                $this->transaction->addObject($invoice)->save();
                $invoice->setTransactionId($txId);
                $invoice->setSendEmail(true);

                if ($paymentIsPartial) {
                    $currentOrder->setState(\Magento\Sales\Model\Order::STATE_PAYMENT_REVIEW);
                    $currentOrder->setStatus(\Magento\Sales\Model\Order::STATE_PAYMENT_REVIEW);
                    $payment_message = __('OrderId: %1 - Digiwallet transactionId: %2 - Partial payment made: %3',
                        $orderId,
                        $txId,
                        number_format($consumber_info['bw_paid_amount'] / 100, 2)
                    );
                    $currentOrder->addStatusToHistory(\Magento\Sales\Model\Order::STATE_PAYMENT_REVIEW, $payment_message, true);
                }
                else {
                    $payment_message = __('OrderId: %1 - Digiwallet transactionId: %2 - Full payment made: %3',
                        $orderId,
                        $txId,
                        $currentOrder->getBaseCurrency()->formatTxt($currentOrder->getGrandTotal())
                    );
                    $currentOrder->setState(\Magento\Sales\Model\Order::STATE_PROCESSING);
                    $currentOrder->setStatus(\Magento\Sales\Model\Order::STATE_PROCESSING);
                    $currentOrder->addStatusToHistory(\Magento\Sales\Model\Order::STATE_PROCESSING, $payment_message, true);
                    $currentOrder->setIsInProcess(true);
                }

                $currentOrder->save();

                // Add transaction for refunable
                $payment = $currentOrder->getPayment();
                $payment->setLastTransId($txId);
                $payment->setTransactionId($txId);
                $orderTransactionId = $payment->getTransactionId();
                $transaction = $this->transactionBuilder->setPayment($payment)
                    ->setOrder($currentOrder)
                    ->setTransactionId($payment->getTransactionId())
                    ->build(\Magento\Sales\Model\Order\Payment\Transaction::TYPE_ORDER);
                $payment->setParentTransactionId($transaction->getTransactionId());
                $payment->addTransactionCommentsToOrder($transaction, $payment_message);
                $payment->save();

                $this->getResponse()->setBody($paymentIsPartial ? 'Partially paid...' : 'Fully paid...');
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
                $this->scopeConfig->getValue('payment/bankwire/email_template/failure'),
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
