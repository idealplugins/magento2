<?php
namespace Targetpay\Mrcash\Controller\Mrcash;

/**
 * Targetpay Mrcash ReturnAction Controller
 *
 * @method GET
 */
class ReturnAction extends \Magento\Framework\App\Action\Action
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
     * @var \Targetpay\Mrcash\Model\Mrcash
     */
    protected $mrcash;

    /**
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Magento\Sales\Model\Order $order
     * @param \Magento\Checkout\Model\Cart $cart
     * @param \Magento\Framework\App\ResourceConnection $resourceConnection
     * @param \Targetpay\Mrcash\Model\Mrcash $mrcash
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Framework\App\ResourceConnection $resourceConnection,
        \Targetpay\Mrcash\Model\Mrcash $mrcash
    ) {
        parent::__construct($context);
        $this->checkoutSession = $checkoutSession;
        $this->resoureConnection = $resourceConnection;
        $this->mrcash = $mrcash;
    }

    /**
     * When a customer return to website from Targetpay Mrcash gateway.
     *
     * @return void|\Magento\Framework\Controller\Result\Redirect
     */
    public function execute()
    {
        /** @var \Magento\Framework\Controller\Result\Redirect $resultRedirect */
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);

        $orderId = (int) $this->getRequest()->get('order_id');
        $db = $this->resoureConnection->getConnection();
        $sql = "SELECT `paid` FROM `targetpay` 
                WHERE `order_id` = " . $db->quote($orderId) . "
                AND method=" . $db->quote($this->mrcash->getMethodType());
        $result = $db->fetchAll($sql);

        if (isset($result[0]['paid']) && $result[0]['paid']) {
            $this->_redirect('checkout/onepage/success', ['_secure' => true]);
        } else {
            $this->checkoutSession->restoreQuote();
            return $resultRedirect->setPath('checkout/cart');
        }
    }
}
