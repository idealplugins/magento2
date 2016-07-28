<?php
namespace Targetpay\Paysafecard\Controller\Paysafecard;

use Magento\Framework\Controller\ResultFactory;

/**
 * Targetpay Paysafecard ReturnAction Controller
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
     * @var \Targetpay\Paysafecard\Model\Paysafecard
     */
    protected $paysafecard;

    /**
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param \Magento\Framework\App\ResourceConnection $resourceConnection
     * @param \Targetpay\Paysafecard\Model\Paysafecard $paysafecard
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Framework\App\ResourceConnection $resourceConnection,
        \Targetpay\Paysafecard\Model\Paysafecard $paysafecard
    ) {
        parent::__construct($context);
        $this->checkoutSession = $checkoutSession;
        $this->resoureConnection = $resourceConnection;
        $this->paysafecard = $paysafecard;
    }

    /**
     * When a customer return to website from Targetpay Paysafecard gateway.
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
                AND method=" . $db->quote($this->paysafecard->getMethodType());
        $result = $db->fetchAll($sql);

        if (isset($result[0]['paid']) && $result[0]['paid']) {
            $this->_redirect('checkout/onepage/success', ['_secure' => true]);
        } else {
            $this->checkoutSession->restoreQuote();
            return $resultRedirect->setPath('checkout/cart');
        }
    }
}
