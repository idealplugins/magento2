<?php
namespace Digiwallet\DPaypal\Controller\DPaypal;

use Digiwallet\DPaypal\Controller\DPaypalBaseAction;

/**
 * Digiwallet DPaypal Report Controller
 *
 * @method POST
 */
class Report extends DPaypalBaseAction
{
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
    ) {
        parent::__construct($context, $resourceConnection, $localeResolver, $scopeConfig, $transaction, $transportBuilder, $order, $dpaypal, $checkoutSession, $transactionRepository, $transactionBuilder);
    }
    
    /**
     * When a customer return to website from Digiwallet DPaypal gateway after a payment is marked as successful.
     * This is an asynchronous call.
     *
     * @return void|string
     */
    public function execute()
    {
        $orderId = (int) $this->getRequest ()->getParam('order_id');
        $txId = $this->getRequest()->getParam('acquirerID', null); // Note: Divergent parameter naming for PayPal, e.g. PAY-8EK778223B308454ULHSLEPI
        if (!isset($txId)) {
            $this->getResponse()->setBody("invalid callback, trxid missing");
            return;
        }

        $db = $this->resoureConnection->getConnection();
        $tableName   = $this->resoureConnection->getTableName('digiwallet_transaction');
        $sql = "SELECT `paid` FROM ".$tableName."
                WHERE `order_id` = " . $db->quote($orderId) . " 
                AND `digi_txid` = " . $db->quote($txId) . " 
                AND method=" . $db->quote($this->dpaypal->getMethodType());

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
        if(!parent::checkTargetPayResult($txId, $orderId))
        {
            /* Send failure payment email to customer */
            $currentOrder = $this->order->loadByIncrementId($orderId);
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
            $this->getResponse()->setBody("Not paid ... ");
        }

        echo "(Magento, 15-06-2016)";
        return;
    }
}
