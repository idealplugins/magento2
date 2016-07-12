<?php
namespace Targetpay\Creditcard\Controller\Creditcard;

/**
 * Targetpay Creditcard BankReturn Controller
 *
 * @method GET
 */
class BankReturn extends \Magento\Framework\App\Action\Action
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
     * @var \Magento\Checkout\Model\Cart
     */
    protected $cart;
    /**
     * @var \Magento\Framework\App\ResourceConnection
     */
    protected $resoureConnection;
    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Magento\Sales\Model\Order $order
     * @param \Magento\Checkout\Model\Cart $cart
     * @param \Magento\Framework\App\ResourceConnection $resourceConnection
     * @param \Psr\Log\LoggerInterface $logger
     * @param \Targetpay\Creditcard\Model\Creditcard $creditcard
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Sales\Model\Order $order,
        \Magento\Checkout\Model\Cart $cart,
        \Magento\Framework\App\ResourceConnection $resourceConnection,
        \Psr\Log\LoggerInterface $logger,
        \Targetpay\Creditcard\Model\Creditcard $creditcard
    ) {
        $this->order = $order;
        $this->cart = $cart;
        $this->resoureConnection = $resourceConnection;
        $this->logger = $logger;
        $this->creditcard = $creditcard;
        parent::__construct($context);
    }

    /**
     * When a customer return to website from Targetpay Creditcard gateway.
     *
     * @return void|\Magento\Framework\Controller\Result\Redirect
     */
    public function execute()
    {
        $orderId = (int) $this->getRequest()->get('order_id');
        $db = $this->resoureConnection->getConnection();
        $sql = "SELECT `paid` FROM `targetpay` 
                WHERE `order_id` = " . $db->quote($orderId) . "
                AND method=" . $db->quote($this->creditcard->getMethodType());
        $result = $db->fetchAll($sql);
        $paid = $result[0]['paid'];

        if ($paid) {
            $this->_redirect('checkout/onepage/success', ['_secure' => true]);
        } else {
            $order = $this->order->loadByIncrementId($orderId);
            $orderItems = $order->getItemsCollection();
            foreach ($orderItems as $orderItem) {
                try {
                    $this->cart->addOrderItem($orderItem);
                } catch (Exception $e) {
                }
            }
            $this->cart->save();

            $this->_redirect('checkout/cart');
        }
    }
}
