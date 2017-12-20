<?php
namespace Digiwallet\DPaypal\Controller\DPaypal;

use Magento\Framework\Controller\ResultFactory;
use Digiwallet\DPaypal\Controller\DPaypalBaseAction;

/**
 * Digiwallet DPaypal ReturnAction Controller
 *
 * @method GET
 */
class ReturnAction extends DPaypalBaseAction
{
    /**
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Magento\Framework\App\ResourceConnection $resourceConnection
     * @param \Psr\Log\LoggerInterface $logger
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
     * When a customer return to website from Digiwallet DPaypal gateway.
     *
     * @return void|\Magento\Framework\Controller\Result\Redirect
     */
    public function execute()
    {
        /* @var \Magento\Framework\Controller\Result\Redirect $resultRedirect */
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        $txId = $this->getRequest()->getParam('paypalid', null); // (sic) PAY-8EK778223B308454ULHSLEPI number
        if (!isset($txId)) {
            $this->checkoutSession->restoreQuote();
            return $resultRedirect->setPath('checkout/cart');
        }
        
        $orderId = (int) $this->getRequest()->get('order_id');
        $db = $this->resoureConnection->getConnection();
        $tableName   = $this->resoureConnection->getTableName('digiwallet_transaction');
        $sql = "SELECT `paid` FROM ".$tableName." 
                WHERE `order_id` = " . $db->quote($orderId) . "
                AND `digi_txid` = " . $db->quote($txId) . "
                AND method=" . $db->quote($this->dpaypal->getMethodType());
        $result = $db->fetchAll($sql);
        
        if (isset($result[0]['paid']) && $result[0]['paid']) {            
            $this->_redirect('checkout/onepage/success', ['_secure' => true]);
        } else {
            if(parent::checkTargetPayResult($txId, $orderId)){
                $this->_redirect('checkout/onepage/success', ['_secure' => true, 'paid' => "1"]);
            } else {
                $this->checkoutSession->restoreQuote();
                $this->_redirect('checkout/cart', ['_secure' => true]);
            }
        }
    }
}
