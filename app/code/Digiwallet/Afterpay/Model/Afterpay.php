<?php
namespace Digiwallet\Afterpay\Model;

use Digiwallet\Core\TargetPayCore;
use Digiwallet\Core\TargetPayRefund;
use Digiwallet\Afterpay\Controller\AfterpayValidationException;

class Afterpay extends \Magento\Payment\Model\Method\AbstractMethod
{

    const METHOD_CODE = 'afterpay';

    const METHOD_TYPE = 'AFP';

    protected $maxAmount = 10000;

    protected $minAmount = 0.84;

    /**
     * Tax applying percent
     * @var array
     */
    protected $array_tax = [
        1 => 21,
        2 => 6,
        3 => 0,
        4 => 'none'
    ];

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
    protected $_canRefund = false;

    /**
     * Availability option
     *
     * @var bool
     */
    protected $_canVoid = true;

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
    protected $_canUseForMultishipping = true;

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
    private $tpMethod = self::METHOD_TYPE;

    /**
     *
     * @var \Magento\Framework\Url
     */
    private $urlBuilder;

    /**
     *
     * @var \Magento\Checkout\Model\Session
     */
    private $checkoutSession;

    /**
     *
     * @var \Magento\Sales\Model\Order
     */
    private $order;

    /**
     *
     * @var \Magento\Framework\Locale\Resolver
     */
    private $localeResolver;

    /**
     *
     * @var \Magento\Framework\App\ResourceConnection
     */
    private $resoureConnection;

    /**
     * *
     * Status of payment
     *
     * @var unknown
     */
    private $status;

    /**
     * Transaction Id
     *
     * @var unknown
     */
    private $trxid;

    /**
     * Reject when $status = "Rejected"
     *
     * @var unknown
     */
    private $reject_error;

    /**
     * Redirect Url when $status = "Incomplete"
     *
     * @var unknown
     */
    private $redirect_url;

    /**
     * Get the return URL
     *
     * @var unknown
     */
    private $return_url;

    /**
     * Current request parameter
     * @var \Magento\Framework\App\RequestInterface
     */
    private $request;
    /**
     *
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
     *            @SuppressWarnings(PHPMD.ExcessiveParameterList)
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
        )
    {
        parent::__construct($context, $registry, $extensionFactory, $customAttributeFactory, $paymentData, $scopeConfig, $logger, $resource, $resourceCollection, $data);

        $this->urlBuilder = $urlBuilder;
        $this->checkoutSession = $checkoutSession;
        $this->order = $order;
        $this->localeResolver = $localeResolver;
        $this->resoureConnection = $resourceConnection; 
        $this->request = $requestInterface;       
    }
    /***
     * Get product tax by Digiwallet
     * @param unknown $val
     * @return number
     */
    private function getTax($val)
    {
        if(empty($val)) return 4; // No tax
        else if($val >= 21) return 1;
        else if($val >= 6) return 2;
        else return 3;
    }
    /**
     * Format phonenumber by NL/BE
     * 
     * @param unknown $country
     * @param unknown $phone
     * @return unknown
     */
    private static function format_phone($country, $phone) {
        $function = 'format_phone_' . strtolower($country);
        if(method_exists('Digiwallet\Afterpay\Model\Afterpay', $function)) {
            return self::$function($phone);
        }
        else {
            echo "unknown phone formatter for country: ". $function;
            exit;
        }
        return $phone;
    }
    
    /**
     * Format phone number
     * 
     * @param unknown $phone
     * @return string|mixed
     */
    private static function format_phone_nld($phone) {
        // note: making sure we have something
        if(!isset($phone{3})) { return ''; }
        // note: strip out everything but numbers
        $phone = preg_replace("/[^0-9]/", "", $phone);
        $length = strlen($phone);
        switch($length) {
            case 9:
                return "+31".$phone;
                break;
            case 10:
                return "+31".substr($phone, 1);
                break;
            case 11:
            case 12:
                return "+".$phone;
                break;
            default:
                return $phone;
                break;
        }
    }
    
    /**
     * Format phone number
     * 
     * @param unknown $phone
     * @return string|mixed
     */
    private static function format_phone_bel($phone) {
        // note: making sure we have something
        if(!isset($phone{3})) { return ''; }
        // note: strip out everything but numbers
        $phone = preg_replace("/[^0-9]/", "", $phone);
        $length = strlen($phone);
        switch($length) {
            case 9:
                return "+32".$phone;
                break;
            case 10:
                return "+32".substr($phone, 1);
                break;
            case 11:
            case 12:
                return "+".$phone;
                break;
            default:
                return $phone;
                break;
        }
    }
    /**
     * Breadown street address
     * @param unknown $street
     * @return NULL[]|string[]|unknown[]
     */
    private static function breakDownStreet($street)
    {
        $out = [];
        $addressResult = null;
        preg_match("/(?P<address>\D+) (?P<number>\d+) (?P<numberAdd>.*)/", $street, $addressResult);
        if(!$addressResult) {
            preg_match("/(?P<address>\D+) (?P<number>\d+)/", $street, $addressResult);
        }
        $out['street'] = array_key_exists('address', $addressResult) ? $addressResult['address'] : null;
        $out['houseNumber'] = array_key_exists('number', $addressResult) ? $addressResult['number'] : null;
        $out['houseNumberAdd'] = array_key_exists('numberAdd', $addressResult) ? trim(strtoupper($addressResult['numberAdd'])) : null;
        return $out;
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

        if (! $order->getId()) {
            throw new \Magento\Checkout\Exception(__('Cannot load order #' . $order->getRealOrderId()));
        }

        if ($order->getGrandTotal() < $this->minAmount) {
            throw new \Magento\Checkout\Exception(__('Het totaalbedrag is lager dan het minimum van ' . $this->minAmount . ' euro voor ' . Afterpay::METHOD_CODE));
        }

        if ($order->getGrandTotal() > $this->maxAmount) {
            throw new \Magento\Checkout\Exception(__('Het totaalbedrag is hoger dan het maximum van ' . $this->maxAmount . ' euro voor ' . Afterpay::METHOD_CODE));
        }

        $orderId = $order->getRealOrderId();
        $language = ($this->localeResolver->getLocale() == 'nl_NL') ? "nl" : "en";
        $testMode = (bool) $this->_scopeConfig->getValue('payment/afterpay/testmode');

        $digiCore = new TargetPayCore($this->tpMethod, $this->_scopeConfig->getValue('payment/afterpay/rtlo'), $language, $testMode);
        $digiCore->setAmount(round($order->getGrandTotal() * 100));
        $digiCore->setDescription("Order #$orderId");
        $digiCore->setReturnUrl($this->urlBuilder->getUrl('afterpay/afterpay/return', [
            '_secure' => true,
            'order_id' => $orderId
        ]));
        $digiCore->setReportUrl($this->urlBuilder->getUrl('afterpay/afterpay/report', [
            '_secure' => true,
            'order_id' => $orderId
        ]));

        $this->return_url = $digiCore->getReturnUrl();
        $digiCore->bindParam('email', $order->getCustomerEmail());
        $digiCore->bindParam('userip', $_SERVER["REMOTE_ADDR"]);

        
        // Build invoice lines
        $invoice_lines = null;
        $total_amount_by_product = 0;
        foreach ($order->getAllItems() as $item) {
            $invoice_lines[] = [
                'productCode' => $item->getProduct()->getId(),
                'productDescription' => $item->getProduct()->getName(),
                'quantity' => (int) $item->getQtyOrdered(),
                'price' => $item->getPrice(),
                'taxCategory' => ($item->getPrice() > 0) ? $this->getTax(100 * $item->getTaxAmount() / $item->getPrice()) : 3
            ];
            $total_amount_by_product += $item->getPrice();
        }
        // Update to fix the total amount and item price
        if($total_amount_by_product < $order->getGrandTotal()){
            $invoice_lines[] = [
                'productCode' => "000000",
                'productDescription' => "Other fee (shipping, additional fees)",
                'quantity' => 1,
                'price' => $order->getGrandTotal() - $total_amount_by_product,
                'taxCategory' => 1
            ];
        }
        // Add invoice line to payment
        if($invoice_lines != null && !empty($invoice_lines)){
            $digiCore->bindParam('invoicelines', json_encode($invoice_lines));
        }
        $billingAddress = $order->getBillingAddress();
        $shippingAddress = $order->getShippingAddress();
        // Fix country code to ISO 639-2
        $billingCountry = ($billingAddress == null ? "NLD" : (strtoupper($billingAddress->getCountryId()) == 'BE' ? 'BEL' : 'NLD'));
        $shippingCountry = ($shippingAddress == null ? "NLD" : (strtoupper($shippingAddress->getCountryId()) == 'BE' ? 'BEL' : 'NLD'));
        // Build billing address
        $billing_addresses = ($billingAddress == null ? array() : $billingAddress->getStreet());
        $billing_address_1 = count($billing_addresses) > 0 ? $billing_addresses[0] : "";
        $billing_address_2 = count($billing_addresses) > 1 ? $billing_addresses[1] : "";
        $streetParts = self::breakDownStreet("");
        if(empty($billing_address_2)) {
            $streetParts = self::breakDownStreet($billing_address_1);
        }
        
        $digiCore->bindParam('billingstreet', empty($streetParts['street']) ? $billing_address_1 : $streetParts['street']);
        $digiCore->bindParam('billinghousenumber', empty($streetParts['houseNumber'].$streetParts['houseNumberAdd']) ? $billing_address_2 : $streetParts['houseNumber'].$streetParts['houseNumberAdd']);
        $digiCore->bindParam('billingpostalcode', $billingAddress == null ? "" : str_replace(" ", "", $billingAddress->getPostcode()));
        $digiCore->bindParam('billingcity', $billingAddress == null ? "" : $billingAddress->getCity());
        $digiCore->bindParam('billingpersonemail', $billingAddress == null ? "" : $billingAddress->getEmail());
        $digiCore->bindParam('billingpersoninitials', "");
        $digiCore->bindParam('billingpersongender', "");
        $digiCore->bindParam('billingpersonsurname', $billingAddress == null ? "" : $billingAddress->getFirstname());
        $digiCore->bindParam('billingcountrycode', $billingCountry);
        $digiCore->bindParam('billingpersonlanguagecode', $billingCountry);
        $digiCore->bindParam('billingpersonbirthdate', "");
        $digiCore->bindParam('billingpersonphonenumber', self::format_phone($billingCountry, $billingAddress == null ? "" : $billingAddress->getTelephone()));
        // Build shipping address
        $shipping_addresses = $shippingAddress == null ? array() : $shippingAddress->getStreet();
        $shipping_address_1 = count($shipping_addresses) > 0 ? $shipping_addresses[0] : "";
        $shipping_address_2 = count($shipping_addresses) > 1 ? $shipping_addresses[1] : "";
        $streetParts = self::breakDownStreet("");
        if(empty($shipping_address_2)){
            $streetParts = self::breakDownStreet($shipping_address_1);
        }
        $digiCore->bindParam('shippingstreet', empty($streetParts['street']) ? $shipping_address_1 : $streetParts['street']);
        $digiCore->bindParam('shippinghousenumber', empty($streetParts['houseNumber'].$streetParts['houseNumberAdd']) ? $shipping_address_2 : $streetParts['houseNumber'].$streetParts['houseNumberAdd']);
        $digiCore->bindParam('shippinghousenumber', "");
        $digiCore->bindParam('shippingpostalcode', $shippingAddress == null ? "" : str_replace(" ", "", $shippingAddress->getPostcode()));
        $digiCore->bindParam('shippingcity', $shippingAddress == null ? "" : $shippingAddress->getCity());
        $digiCore->bindParam('shippingpersonemail', $shippingAddress == null ? "" : $shippingAddress->getEmail());
        $digiCore->bindParam('shippingpersoninitials', "");
        $digiCore->bindParam('shippingpersongender', "");
        $digiCore->bindParam('shippingpersonsurname', $shippingAddress == null ? "" : $shippingAddress->getFirstname());
        $digiCore->bindParam('shippingcountrycode', $shippingCountry);
        $digiCore->bindParam('shippingpersonlanguagecode', $shippingCountry);
        $digiCore->bindParam('shippingpersonbirthdate', "");
        $digiCore->bindParam('shippingpersonphonenumber', self::format_phone($shippingCountry, $shippingAddress == null ? "" : $shippingAddress->getTelephone()));

        // Start payment
        $result = $digiCore->startPayment();

        if (! $result) {
            $exception = new AfterpayValidationException($digiCore->getErrorMessage());
            if ($exception->IsValidationError()) {
                throw $exception;
            } else {
                throw new \Exception(__("Digiwallet error: {$digiCore->getErrorMessage()}"));
            }
        }

        $result_str = $digiCore->getMoreInformation();
        // Process return message
        list ($this->trxid, $this->status) = explode("|", $result_str);
        if (strtolower($this->status) != "captured") {
            list ($this->trxid, $this->status, $ext_info) = explode("|", $result_str);
            if (strtolower($this->status) == "rejected") {
                $this->reject_error = $ext_info;
            } else {
                $this->redirect_url = $ext_info;
            }
        }

        $db = $this->resoureConnection->getConnection();
        $tableName = $this->resoureConnection->getTableName('digiwallet_transaction');
        $db->query("
            INSERT INTO " . $tableName . " SET
                `order_id`=" . $db->quote($orderId) . ",
                `method`=" . $db->quote($this->tpMethod) . ",
                `digi_txid`=" . $db->quote($this->trxid) . ",
                `digi_response` = " . $db->quote($this->status) . ",
                `more`=" . $db->quote($result_str));
        return $this;
    }

    /**
     * Get the status result
     *
     * @return \Digiwallet\Afterpay\Model\unknown
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * Get the transaction id
     *
     * @return \Digiwallet\Afterpay\Model\unknown
     */
    public function getTransactionId()
    {
        return $this->trxid;
    }

    /**
     * Get the Rejected message when status is "Rejected"
     *
     * @return \Digiwallet\Afterpay\Model\unknown
     */
    public function getRejectedMessage()
    {
        return $this->reject_error;
    }

    /**
     * Get the Url when status is "Incomplete"
     *
     * @return \Digiwallet\Afterpay\Model\unknown
     */
    public function getRedirectUrl()
    {
        return $this->redirect_url;
    }

    /**
     * Get the return url after starting payment
     *
     * @return \Digiwallet\Afterpay\Model\unknown
     */
    public function getReturnUrl()
    {
        return $this->return_url;
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
