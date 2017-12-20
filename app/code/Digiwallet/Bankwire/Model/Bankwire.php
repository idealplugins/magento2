<?php
namespace Digiwallet\Bankwire\Model;

use Digiwallet\Core\TargetPayCore;
use Digiwallet\Core\TargetPayRefund;

class Bankwire extends \Magento\Payment\Model\Method\AbstractMethod
{

    const METHOD_CODE = 'bankwire';
    const METHOD_TYPE = 'BW';
    protected $maxAmount = 10000;
    protected $minAmount = 0.84;
    protected $salt = 'e381277';
    
    /**
     * Payment method code
     *
     * @var string
     */
    protected $_code = self::METHOD_CODE;

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
     * Payment method type
     *
     * @var string
     */
    private $tpMethod  = self::METHOD_TYPE;

    /**
     * @var \Magento\Framework\Url
     */
    private $urlBuilder;

    /**
     * @var \Magento\Checkout\Model\Session
     */
    private $checkoutSession;

    /**
     * @var \Magento\Sales\Model\Order
     */
    private $order;

    /**
     * @var \Magento\Framework\Locale\Resolver
     */
    private $localeResolver;

    /**
     * @var \Magento\Framework\App\ResourceConnection
     */
    private $resoureConnection;
    
    /**
     * Current request parameter
     * @var \Magento\Framework\App\RequestInterface
     */
    private $request;
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
     * @param \Magento\Framework\App\RequestInterface $requestInterface
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
        \Magento\Framework\App\RequestInterface $requestInterface = null,
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
        $this->request = $requestInterface;
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
            throw new \Magento\Checkout\Exception(__('Cannot load order #' . $order->getRealOrderId()));
        }

        if ($order->getGrandTotal() < $this->minAmount) {
            throw new \Magento\Checkout\Exception(
                __('Het totaalbedrag is lager dan het minimum van ' . $this->minAmount . ' euro voor ' . Bankwire::METHOD_CODE)
            );
        }

        if ($order->getGrandTotal() > $this->maxAmount ) {
            throw new \Magento\Checkout\Exception(
                __('Het totaalbedrag is hoger dan het maximum van ' . $this->maxAmount . ' euro voor ' . Bankwire::METHOD_CODE)
                );
        }
        
        $orderId = $order->getRealOrderId();
        $language = ($this->localeResolver->getLocale() == 'nl_NL') ? "nl" : "en";
        $testMode = (bool) $this->_scopeConfig->getValue('payment/bankwire/testmode');
        
        $digiCore = new TargetPayCore(
            $this->tpMethod,
            $this->_scopeConfig->getValue('payment/bankwire/rtlo'),
            $language,
            $testMode
        );
        $digiCore->setAmount(round($order->getGrandTotal() * 100));
        $digiCore->setDescription("Order #$orderId");
        $digiCore->setReturnUrl(
            $this->urlBuilder->getUrl('bankwire/bankwire/return', ['_secure' => true, 'order_id' => $orderId])
        );
        $digiCore->setReportUrl(
            $this->urlBuilder->getUrl('bankwire/bankwire/report', ['_secure' => true, 'order_id' => $orderId])
        );
        
        $digiCore->bindParam('salt', $this->salt);
        $digiCore->bindParam('email', $order->getCustomerEmail());
        $digiCore->bindParam('userip', $_SERVER["REMOTE_ADDR"]);

        $result = $digiCore->startPayment();

        if (!$result) {            
            throw new \Exception(__("Digiwallet error: {$digiCore->getErrorMessage()}"));
        }
        // Format Ex: XXXX-XX-YY-ZZZZ|5940.74.231|NL44ABNA0594074231|ABNANL2A|St. Derdengelden TargetMedia|ABN Amro        
        $db = $this->resoureConnection->getConnection();
        $tableName   = $this->resoureConnection->getTableName('digiwallet_transaction');
        $db->query("
        INSERT INTO ".$tableName." SET
            `order_id`=" . $db->quote($orderId).",
            `method`=" . $db->quote($this->tpMethod) . ",
            `digi_txid`=" . $db->quote($digiCore->getTransactionId()) .",
            `digi_response` = " . $db->quote($digiCore->getMoreInformation()) . ",
            `more` = " . $db->quote($digiCore->getMoreInformation())
            );
        return $digiCore;
    }

    /**
     * Retrieve payment method type
     *
     * @return string
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getMethodType()
    {
        if (empty($this->tpMethod)) {
            throw new \Magento\Framework\Exception\LocalizedException(__('We cannot retrieve the payment method type'));
        }
        return $this->tpMethod;
    }

    /**
     * Bankwire Salt code
     * 
     * @return string
     */
    public function getSalt()
    {
        return $this->salt;
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
    /**
     * Check refund availability
     *
     * @return bool
     * @api
     */
    public function canRefund()
    {
        return !empty ($this->_scopeConfig->getValue('payment/' .  self::METHOD_CODE . '/apitoken'));
    }
    /**
     * Check partial refund availability for invoice
     *
     * @return bool
     * @api
     */
    public function canRefundPartialPerInvoice()
    {
        return !empty($this->_scopeConfig->getValue('payment/' .  self::METHOD_CODE . '/apitoken'));
    }
    /**
     * Refund specified amount for payment
     *
     * @param \Magento\Framework\DataObject|InfoInterface $payment
     * @param float $amount
     * @return $this
     * @throws \Magento\Framework\Exception\LocalizedException
     * @api
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function refund(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        $api_token = $this->_scopeConfig->getValue('payment/' .  self::METHOD_CODE . '/apitoken');
        $refundObj = new TargetPayRefund(self::METHOD_TYPE, $amount, $api_token, $payment, $this->resoureConnection);
        $refundObj->setLanguage(($this->localeResolver->getLocale() == 'nl_NL') ? "nl" : "en");
        $refundObj->setLayoutCode($this->_scopeConfig->getValue('payment/' .  self::METHOD_CODE . '/rtlo'));
        $refundObj->setTestMode((bool) $this->_scopeConfig->getValue('payment/' .  self::METHOD_CODE . '/testmode'));
        $refundObj->refund();
    }
}

