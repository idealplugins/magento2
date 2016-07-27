<?php
namespace Targetpay\Mrcash\Model;

use Targetpay\TargetPayCore;

class Mrcash extends \Magento\Payment\Model\Method\AbstractMethod
{

    const METHOD_CODE = 'mrcash';
    const METHOD_TYPE = 'MRC';
    const APP_ID = 'f8ca4794a1792886bb88060ca0685c1e';

    /**
     * Payment method code
     *
     * @var string
     */
    protected $_code = self::METHOD_CODE;
    /**
     * Payment method type
     *
     * @var string
     */
    protected $_tp_method  = self::METHOD_TYPE;
    /**
     * Payment app id
     *
     * @var string
     */
    protected $appId  = self::APP_ID;
    /**
     * Availability option
     *
     * @var bool
     */
    protected $_isGateway = true;
    /**
     * Availability option
     *
     * @var bool
     */
    protected $_canAuthorize = true;
    /**
     * Availability option
     *
     * @var bool
     */
    protected $_canCapture = true;
    /**
     * Availability option
     *
     * @var bool
     */
    protected $_canCapturePartial = false;
    /**
     * Availability option
     *
     * @var bool
     */
    protected $_canRefund  = false;
    /**
     * Availability option
     *
     * @var bool
     */
    protected $_canVoid  = true;
    /**
     * Availability option
     *
     * @var bool
     */
    protected $_canUseInternal = true;
    /**
     * Availability option
     *
     * @var bool
     */
    protected $_canUseCheckout = true;
    /**
     * Availability option
     *
     * @var bool
     */
    protected $_canUseForMultishipping  = true;
    /**
     * Availability option
     *
     * @var bool
     */
    protected $_canSaveCc = false;
    /**
     * @var \Magento\Framework\Url
     */
    protected $urlBuilder;
    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $checkoutSession;
    /**
     * @var \Magento\Sales\Model\Order
     */
    protected $order;
    /**
     * @var \Magento\Framework\Locale\Resolver
     */
    protected $localeResolver;
    /**
     * @var \Magento\Framework\App\ResourceConnection
     */
    protected $resoureConnection;

    /**
     * @param \Magento\Framework\Model\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory
     * @param \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory
     * @param \Magento\Payment\Helper\Data $paymentData
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Payment\Model\Method\Logger $logger
     * @param \Magento\Framework\Url $urlBuilder
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param \Magento\Sales\Model\Order $orderFactory
     * @param \Magento\Framework\Locale\Resolver $localeResolver
     * @param \Magento\Framework\Model\ResourceModel\AbstractResource $resource
     * @param \Magento\Framework\Data\Collection\AbstractDb $resourceCollection
     * @param array $data
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory,
        \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory,
        \Magento\Payment\Helper\Data $paymentData,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Payment\Model\Method\Logger $logger,
        \Magento\Framework\Url $urlBuilder,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Sales\Model\Order $order,
        \Magento\Framework\Locale\Resolver $localeResolver,
        \Magento\Framework\App\ResourceConnection $resourceConnection,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        parent::__construct(
            $context,
            $registry,
            $extensionFactory,
            $customAttributeFactory,
            $paymentData,
            $scopeConfig,
            $logger,
            $resource,
            $resourceCollection,
            $data
        );

        $this->urlBuilder = $urlBuilder;
        $this->checkoutSession = $checkoutSession;
        $this->order = $order;
        $this->localeResolver = $localeResolver;
        $this->resoureConnection = $resourceConnection;
    }

    /**
     * Start payment
     *
     * @param integer $bankId
     *
     * @return string Bank url
     * @throws \Magento\Checkout\Exception
     */
    public function setupPayment($bankId = false)
    {
        /** @var \Magento\Sales\Model\Order $order */
        $order = $this->getOrder();

        if (!$order->getId()) {
            throw new \Magento\Checkout\Exception(__('Cannot load order #' . $lastOrderId));
        }

        if ($order->getGrandTotal() < 0.84) {
            throw new \Magento\Checkout\Exception(__('The total amount should be at least 0.85'));
        }

        $orderId = $order->getRealOrderId();
        $language = ($this->localeResolver->getLocale() == 'nl_NL') ? "nl" : "en";

        $targetPay = new TargetPayCore(
            $this->_tp_method,
            $this->_scopeConfig->getValue('payment/mrcash/rtlo'),
            $this->appId,
            $language,
            false
        );
        $targetPay->setAmount(round($order->getGrandTotal() * 100));
        $targetPay->setDescription("Order #$orderId");
//         $targetPay->setBankId($bankId);
        $targetPay->setReturnUrl(
            $this->urlBuilder->getUrl('mrcash/mrcash/return', ['_secure' => true, 'order_id' => $orderId])
        );
        $targetPay->setReportUrl(
            $this->urlBuilder->getUrl('mrcash/mrcash/report', ['_secure' => true, 'order_id' => $orderId])
        );
        $bankUrl = $targetPay->startPayment();

        if (!$bankUrl) {
            throw new \Magento\Checkout\Exception(__("TargetPay error: {$targetPay->getErrorMessage()}"));
        }

        $db = $this->resoureConnection->getConnection();
        $db->query("
            INSERT INTO `targetpay` SET 
            `order_id`=" . $db->quote($orderId).",
            `method`=" . $db->quote($this->_tp_method) . ",
            `targetpay_txid`=" . $db->quote($targetPay->getTransactionId()));

        return $bankUrl;
    }

    /**
     * Retrieve payment method type
     *
     * @return string
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getMethodType()
    {
        if (empty($this->_tp_method)) {
            throw new \Magento\Framework\Exception\LocalizedException(__('We cannot retrieve the payment method type'));
        }
        return $this->_tp_method;
    }

    /**
     * Retrieve payment app id
     *
     * @return string
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getAppId()
    {
        if (empty($this->appId)) {
            throw new \Magento\Framework\Exception\LocalizedException(__('We cannot retrieve the payment app id'));
        }
        return $this->appId;
    }

    /**
     * Retrieve current order
     *
     * @return \Magento\Sales\Model\Order
     */
    public function getOrder()
    {
        $orderId = $this->checkoutSession->getLastOrderId();
        return $this->order->load($orderId);
    }
}
