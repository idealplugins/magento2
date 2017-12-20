<?php
namespace Digiwallet\Bankwire\Controller\Bankwire;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Controller\ResultFactory;

/**
 * Digiwallet Bankwire Redirect Controller
 *
 * @method GET
 */
class Success extends \Magento\Framework\App\Action\Action
{
    /**
     * @var \Magento\Framework\App\ResourceConnection
     */
    private $resoureConnection;
    /**
     * @var \Magento\Checkout\Model\Session
     */
    private $checkoutSession;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    private $logger;

    /**
     * @var \Digiwallet\Bankwire\Model\Bankwire
     */
    private $bankwire;
    /**
     * @var \Magento\Framework\View\Result\PageFactory
     */
    private $resultPageFactory;
    /**
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Digiwallet\Bankwire\Model\Bankwire $Bankwire
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param \Psr\Log\LoggerInterface $logger
     * @param \Magento\Framework\View\Result\PageFactory $resultPageFactory
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Framework\App\ResourceConnection $resourceConnection,
        \Digiwallet\Bankwire\Model\Bankwire $bankwire,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Psr\Log\LoggerInterface $logger,
        \Magento\Framework\View\Result\PageFactory $resultPageFactory
    ) {
        parent::__construct($context);
        $this->resoureConnection = $resourceConnection;
        $this->checkoutSession = $checkoutSession;
        $this->logger = $logger;
        $this->bankwire = $bankwire;
        $this->resultPageFactory = $resultPageFactory;
    }

    /**
     * When a customer has ordered and redirect to Digiwallet Bankwire gateway.
     *
     * @return void|\Magento\Framework\Controller\Result\Redirect
     */
    public function execute()
    {
        $trans_id = $this->getRequest()->getParam('trxid');
        $db = $this->resoureConnection->getConnection();
        $tableName   = $this->resoureConnection->getTableName('digiwallet_transaction');
        $sql = "SELECT * FROM ".$tableName."
                WHERE `digi_txid` = " . $db->quote($trans_id) . "
                AND method=" . $db->quote($this->bankwire->getMethodType());
        $result = $db->fetchAll($sql);
        if (!count($result) || ((!empty($result[0]['paid'])) ? true : false)) {
            /* @var \Magento\Framework\Controller\Result\Redirect $resultRedirect */
            $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
            return $resultRedirect->setPath('checkout/cart');
        }
        // Show result page
        $resultPage = $this->resultPageFactory->create();
        $resultPage->getConfig()->getTitle()->set(__('Your order has been placed'));
        return $resultPage;
    }
}
