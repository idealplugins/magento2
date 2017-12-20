<?php
namespace Digiwallet\Core;

/**
 * @file Provides support for Digiwallet iDEAL, Mister Cash and Sofort Banking
 * 
 * @author Yellow Melon B.V.
 *         @url http://www.idealplugins.nl
 *         @release 29-09-2014
 *         @ver 2.5
 *        
 *         Changes:
 *        
 *         v2.1 Cancel url added
 *         v2.2 Verify Peer disabled, too many problems with this
 *         v2.3 Added paybyinvoice (achteraf betalen) and paysafecard (former Wallie)
 *         v2.4 Removed IP_range and deprecated checkReportValidity . Because it is bad practice.
 *         v2.5 Added creditcards by ATOS
 */

/**
 * @class TargetPay Core class
 */
class TargetPayCore
{

    const APP_ID = 'dw_magento2.x_1.0.2'; // Adjust postfix version number with public plugin releases to Git version number

    const MIN_AMOUNT = 0.84;

    const ERR_NO_AMOUNT = "Geen bedrag meegegeven | No amount given";

    const ERR_NO_DESCRIPTION = "Geen omschrijving meegegeven | No description given";

    const ERR_NO_RTLO = "Geen DigiWallet Outlet Identifier bekend; controleer de module instellingen | No Digiwallet Outlet Identifier filled in, check the module settings";
    
    const ERR_NO_TXID = "Er is een onjuist transactie ID opgegeven | An incorrect transaction ID was given";

    const ERR_NO_RETURN_URL = "Geen of ongeldige return URL | No or invalid return URL";

    const ERR_NO_REPORT_URL = "Geen of ongeldige report URL | No or invalid report URL";

    const ERR_IDEAL_NO_BANK = "Geen bank geselecteerd voor iDEAL | No bank selected for iDEAL";

    const ERR_SOFORT_NO_COUNTRY = "Geen land geselecteerd voor Sofort Of niet ondersteunen | No country selected for Sofort or not support";

    const ERR_PAYBYINVOICE = "Fout bij achteraf betalen|Error with paybyinvoice";

    /**
     * Constant array
     *
     * @var array
     */
    private $paymentOptions = [
        "IDE",
        "MRC",
        "DEB",
        "WAL",
        "CC",
        "PYP",
        "BW",
        "AFP"
    ];

    /**
     *
     * @var array
     */
    private $checkAPIs = [
        "IDE" => "https://transaction.digiwallet.nl/ideal/check",
        "MRC" => "https://transaction.digiwallet.nl/mrcash/check",
        "DEB" => "https://transaction.digiwallet.nl/directebanking/check",
        "WAL" => "https://transaction.digiwallet.nl/paysafecard/check",
        "CC" => "https://transaction.digiwallet.nl/creditcard/check",
        "PYP" => "https://transaction.digiwallet.nl/paypal/check",
        "AFP" => "https://transaction.digiwallet.nl/afterpay/check",
        "BW" => "https://transaction.digiwallet.nl/bankwire/check"
    ];

    /**
     *
     * @var array
     */
    private $startAPIs = [
        "IDE" => "https://transaction.digiwallet.nl/ideal/start",
        "MRC" => "https://transaction.digiwallet.nl/mrcash/start",
        "DEB" => "https://transaction.digiwallet.nl/directebanking/start",
        "WAL" => "https://transaction.digiwallet.nl/paysafecard/start",
        "CC" => "https://transaction.digiwallet.nl/creditcard/start",
        "PYP" => "https://transaction.digiwallet.nl/paypal/start",
        "AFP" => "https://transaction.digiwallet.nl/afterpay/start",
        "BW" => "https://transaction.digiwallet.nl/bankwire/start"
    ];

    /**
     *
     * @var string
     */
    private $rtlo = null;

    /**
     *
     * @var boolean
     */
    private $testMode = false;

    /**
     *
     * @var string
     */
    private $language = "nl";

    /**
     * Payment Method
     *
     * @var string
     */
    private $payMethod = "IDE";

    /**
     *
     * @var string
     */
    private $currency = "EUR";

    /**
     *
     * @var string
     */
    private $bankId = null;

    /**
     *
     * @var integer
     */
    private $amount = 0;

    /**
     *
     * @var string
     */
    private $description = null;

    /**
     * %payMethod% will be replaced by the actual payment method just before starting the payment
     *
     * @var string
     */
    private $returnUrl = null;

    /**
     * %payMethod% will be replaced by the actual payment method just before starting the payment
     *
     * @var string
     */
    private $cancelUrl = null;

    /**
     * %payMethod% will be replaced by the actual payment method just before starting the payment
     *
     * @var string
     */
    private $reportUrl = null;

    /**
     *
     * @var string
     */
    private $bankUrl = null;

    /**
     *
     * @var string
     */
    private $transactionId = null;

    /**
     *
     * @var boolean
     */
    private $paidStatus = false;

    /**
     *
     * @var array
     */
    private $consumerInfo = [];

    /**
     *
     * @var string
     */
    private $errorMessage = null;

    /**
     * Additional parameters
     *
     * @var array
     */
    private $parameters = [];

    /**
     * More information
     *
     * @var unknown
     */
    private $moreInformation = null;

    /**
     * Salt parameter for BW
     *
     * @var unknown
     */
    private $salt = null;

    /**
     * The refundID after call refundInvoice
     * @var unknown
     */
    private $refundId = null;

    /**
     * The refund response data
     * @var unknown
     */
    private $refundResponse = null;

    /**
     *
     * @param string $payMethod
     * @param string $rtlo
     * @param string $language
     * @param string $testMode
     * @return boolean
     */
    public function __construct($payMethod, $rtlo = false, $language = "nl", $testMode = false)
    {
        $payMethod = strtoupper($payMethod);
        if (in_array($payMethod, $this->paymentOptions)) {
            $this->payMethod = $payMethod;
        } else {
            return false;
        }
        $this->rtlo = (int) $rtlo;
        $this->testMode = ($testMode) ? '1' : '0';
        $this->language = strtolower(substr($language, 0, 2));
    }

    /**
     * Get list with banks based on PayMethod setting (IDE, ...
     * etc.)
     *
     * @return array
     */
    public function getBankList()
    {
        if ($this->payMethod == 'IDE') {
            $url = "https://transaction.digiwallet.nl/ideal/getissuers?ver=3&format=xml";
        } else {
            $url = "https://transaction.digiwallet.nl/api/idealplugins?banklist=" . urlencode($this->payMethod);
        }
        $xml = $this->httpRequest($url);
        $banks_array = array();
        if (! $xml) {
            $banks_array["IDE0001"] = "Bankenlijst kon niet opgehaald worden bij TargetPay, controleer of curl werkt!";
            $banks_array["IDE0002"] = "  ";
        } else {
            if ($this->payMethod == 'IDE') {
                $p = xml_parser_create();
                xml_parse_into_struct($p, $xml, $banks_object, $index);
                xml_parser_free($p);
                foreach ($banks_object as $bank) {
                    if (empty($bank['attributes']['ID']))
                        continue;
                        $banks_array[$bank['attributes']['ID']] = $bank['value'];
                }
            } else {
                $banks_object = new SimpleXMLElement($xml);
                foreach ($banks_object->bank as $bank) {
                    $banks_array["{$bank->bank_id}"] = "{$bank->bank_name}";
                }
            }
        }
        return $banks_array;
    }

    /**
     * Start transaction with Digiwallet
     *
     * Set at least: amount, description, returnUrl, reportUrl (optional: cancelUrl)
     * In case of iDEAL: bankId
     * In case of Sofort: countryId
     *
     * After starting, it will return a link to the bank if successfull :
     * - Link can also be fetched with getBankUrl()
     * - Get the transaction id via getTransactionId()
     * - Read the errors with getErrorMessage()
     * - Get the actual started payment method, in case of auto-setting, using getPayMethod()
     *
     * @return boolean|string Bank URL is returned if success, otherwise False
     */
    public function startPayment()
    {
        if (! $this->rtlo) {
            $this->errorMessage = self::ERR_NO_RTLO;
            return false;
        }

        if (! $this->amount) {
            $this->errorMessage = self::ERR_NO_AMOUNT;
            return false;
        }

        if (! $this->description) {
            $this->errorMessage = self::ERR_NO_DESCRIPTION;
            return false;
        }

        if (! $this->returnUrl) {
            $this->errorMessage = self::ERR_NO_RETURN_URL;
            return false;
        }

        if (! $this->reportUrl) {
            $this->errorMessage = self::ERR_NO_REPORT_URL;
            return false;
        }

        /*
         if (($this->payMethod == "IDE") && (! $this->bankId)) {
         $this->errorMessage = self::ERR_IDEAL_NO_BANK;
         return false;
         }
         */

        if (($this->payMethod == "DEB") && (! $this->countryId)) {
            $this->errorMessage = self::ERR_SOFORT_NO_COUNTRY;
            return false;
        }

        $this->returnUrl = str_replace("%payMethod%", $this->payMethod, $this->returnUrl);
        $this->cancelUrl = str_replace("%payMethod%", $this->payMethod, $this->cancelUrl);
        $this->reportUrl = str_replace("%payMethod%", $this->payMethod, $this->reportUrl);

        // Startpayment Url builder
        $url = $this->startAPIs[$this->payMethod] . "?rtlo=" . urlencode($this->rtlo);
        $url .= "&app_id=" . urlencode(self::APP_ID);
        $url .= "&bank=" . urlencode($this->bankId);
        $url .= "&amount=" . urlencode($this->amount);
        $url .= "&description=" . urlencode($this->description);
        $url .= "&test=" . $this->testMode;
        $url .= "&userip=" . urlencode($_SERVER["REMOTE_ADDR"]);
        $url .= "&domain=" . urlencode($_SERVER["HTTP_HOST"]);
        $url .= "&returnurl=" . urlencode($this->returnUrl);
        $url .= "&reporturl=" . urlencode($this->reportUrl);
        $url .= ((! empty($this->salt)) ? "&salt=" . urlencode($this->salt) : "");
        $url .= ((! empty($this->cancelUrl)) ? "&cancelurl=" . urlencode($this->cancelUrl) : "");
        // Case by case
        $url .= (($this->payMethod == "BW") ? "&ver=2" : "");
        $url .= (($this->payMethod == "CC") ? "&ver=3" : "");
        $url .= (($this->payMethod == "PYP") ? "&ver=1" : "");
        $url .= (($this->payMethod == "AFP") ? "&ver=1" : "");
        $url .= (($this->payMethod == "IDE") ? "&ver=4&language=nl" : "");
        $url .= (($this->payMethod == "MRC") ? "&ver=2&lang=" . urlencode($this->getLanguage(array(
            "NL",
            "FR",
            "EN"
        ), "NL")) : "");
        $url .= (($this->payMethod == "DEB") ? "&ver=2&type=1&country=" . urlencode($this->countryId) . "&lang=" . urlencode($this->getLanguage(array(
            "NL",
            "EN",
            "DE"
        ), "DE")) : "");

        // Another parameter
        if (is_array($this->parameters)) {
            foreach ($this->parameters as $k => $v) {
                $url .= "&" . $k . "=" . urlencode($v);
            }
        }

        $result = $this->httpRequest($url);

        // Test Demo DW
        // $result = "000000 XXXX-XX-YY-000100|5940.74.231|NL44ABNA0594074231|ABNANL2A|St. Derdengelden TargetMedia|ABN Amro";
        $result_code = substr($result, 0, 6);
        if (($result_code == "000000") || ($result_code == "000001" && $this->payMethod == "CC")) {
            $result = substr($result, 7);
            if ($this->payMethod == 'AFP' || $this->payMethod == 'BW') {
                list ($this->transactionId) = explode("|", $result);
                $this->moreInformation = $result;
                return true; // Process later
            } else {
                list ($this->transactionId, $this->bankUrl) = explode("|", $result);
            }
            return $this->bankUrl;
        } else {
            $this->errorMessage = "Digiwallet antwoordde: " . $result . " | Digiwallet responded with: " . $result;
            return false;
        }
    }

    /**
     * Check transaction with Digiwallet
     * After payment:
     * - Read the errors with getErrorMessage()
     * - Get user information using getConsumerInfo()
     *
     * @param string $payMethodId
     *            Payment method's see above
     * @param string $transactionId
     *            Transaction ID to check
     * @return boolean True if the payment is successfull (or testmode) and false if not
     */
    public function checkPayment($transactionId, $params = [])
    {
        if (! $this->rtlo) {
            $this->errorMessage = self::ERR_NO_RTLO;
            return false;
        }

        if (! $transactionId) {
            $this->errorMessage = self::ERR_NO_TXID;
            return false;
        }

        $url = $this->checkAPIs[$this->payMethod] . "?" . "rtlo=" . urlencode($this->rtlo) . "&" . "trxid=" . urlencode($transactionId) . "&" . "once=0&" . "test=" . (($this->testMode) ? "1" : "0");

        foreach ($params as $k => $v) {
            $url .= "&" . $k . "=" . urlencode($v);
        }

        $result = $this->httpRequest($url);

        if ($this->payMethod == 'AFP'){
            // Stop checking status and transfer result to Afterpay Model to process
            return $result;
        }

        $_result = explode("|", $result);

        $consumerBank = "";
        $consumerName = "";
        $consumerCity = "NOT PROVIDED";

        if (count($_result) == 4) {
            list ($resultCode, $consumerBank, $consumerName, $consumerCity) = $_result;
        } elseif(count($_result) == 3){
            // For BankWire
            list ($resultCode, $due_amount, $paid_amount) = $_result;
            $this->consumerInfo["bw_due_amount"] = $due_amount;
            $this->consumerInfo["bw_paid_amount"] = $paid_amount;
        }else{
            list ($resultCode) = $_result;
        }

        $this->consumerInfo["bankaccount"] = "bank";
        $this->consumerInfo["name"] = "customername";
        $this->consumerInfo["city"] = "city";

        if (($resultCode == "000000 OK")  || ($resultCode == "000001 OK" && $this->payMethod == "CC")) {
            $this->consumerInfo["bankaccount"] = $consumerBank;
            $this->consumerInfo["name"] = $consumerName;
            $this->consumerInfo["city"] = ($consumerCity != "NOT PROVIDED") ? $consumerCity : "";
            $this->paidStatus = true;
            return true;
        } else {
            $this->paidStatus = false;
            $this->errorMessage = $result;
            return false;
        }
    }

    /**
     * Get the refund process response
     *
     * @return \Digiwallet\Core\unknown
     */
    public function getRefundResponse()
    {
        return $this->refundResponse;
    }

    /**
     * Return the refundID
     *
     * @return \Digiwallet\Core\unknown
     */
    public function getRefundID()
    {
        return $this->refundId;
    }

    /**
     * Make a refund transaction and rollback invoice
     *
     * @param array $refundData
     * @param string $token
     * @return boolean
     */
    public function refundInvoice($refundData = array(), $token = "")
    {
        if (empty($token)) {
            $this->errorMessage = __("API Token is empty.");
            return false;
        }
        try
        {
            $api_url = "https://api.digiwallet.nl/refund";
            // Start request refund
            $this->refundResponse = $this->httpRequest($api_url, "POST", $refundData, ['Authorization: Bearer ' . $token]);
            $result = json_decode($this->refundResponse, true);
            if(!empty($result['refundID'])){
                // Sucess
                $this->refundId = $result['refundID'];
                return true;
            } else {
                $this->errorMessage = $result['message'];
                if(!empty($result['errors'])){
                    $this->errorMessage .=  ": ";
                    foreach ($result['errors'] as $key => $value){
                        $this->errorMessage .= " " . $key . ": ";
                        foreach ($value as $k => $val){
                            $this->errorMessage .= $val;
                        }
                    }
                }
                return false;
            }
        }
        catch (\Exception $ex)
        {
            $this->errorMessage = __("Request can't be solved.");
            return false;
        }
    }

    /**
     * Delete and refund
     *
     * @param unknown $transactionId
     * @param string $token
     * @return boolean
     */
    public function deleteRefund($transactionId, $token = "")
    {
        if (empty($token)) {
            $this->errorMessage = __("API Token is empty.");
            return false;
        }
        try
        {
            $api_url = "https://api.digiwallet.nl/refund/" . $this->payMethod . "/" . $transactionId;
            //$api_url = "https://api.digiwallet.nl/refund/MRC/16780347";
            $this->refundResponse = $this->httpRequest($api_url, "DELETE", array(), ['Authorization: Bearer ' . $token]);
            $result = json_decode($this->refundResponse, true);
            if($result != null){
                if(!empty($result['errors'])){
                    foreach ($result['errors'] as $key => $value){
                        $this->errorMessage .= " " . $key . ": ";
                        foreach ($value as $k => $val){
                            $this->errorMessage .= $val;
                        }
                    }
                    return false;
                }
                return true;
            }
            else
            {
                $this->errorMessage = __("Your request can't be solved.");
                return false;
            }
        }
        catch (\Exception $ex)
        {
            $this->errorMessage = __("Your request can't be solved.");
            return false;
        }
    }
    /**
     * Will removed in future versions
     * This function used to act as a redundant check on the validity of reports by checking IP addresses
     * Because this is bad practice and not necessary it is now removed
     *
     * @deprecated
     *
     */
    public function checkReportValidity($post, $server)
    {
        return true;
    }

    /**
     * Handling a http request
     *
     * @param string $url
     *            Requested URL
     * @param string $method
     *            HTTP method. Default is GET
     * @return mixed
     */
    protected function httpRequest($url, $method = "GET", $postParams = array(), $headerParams = array())
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        if ($method == "POST") {
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postParams);
        }
        if(!empty($headerParams)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headerParams);
        }
        $result = curl_exec($ch);
        curl_close($ch);
        return $result;
    }

    /**
     * Bind additional parameter to start request.
     * Safe for chaining.
     *
     * @param string $name
     *            Parameter name
     * @param string $value
     *            Parameter value
     * @return $this the object itself
     */
    public function bindParam($name, $value)
    {
        $this->parameters[$name] = $value;
        return $this;
    }

    /**
     * Set amount value
     *
     * @param integer $amount
     * @return boolean
     */
    public function setAmount($amount)
    {
        $this->amount = round($amount);
        return true;
    }

    /**
     * Retrieve amount
     *
     * @return integer
     */
    public function getAmount()
    {
        return $this->amount;
    }

    /**
     * Set bank id value
     *
     * @param string $bankId
     * @return boolean
     */
    public function setBankId($bankId)
    {
        if ($this->payMethod == "IDE") {
            $this->bankId = $bankId;
        } else {
            $this->bankId = substr($bankId, 0, 4);
        }
        return true;
    }

    /**
     * Retrieve bank id
     *
     * @return string
     */
    public function getBankId()
    {
        return $this->bankId;
    }

    /**
     * Retrieve bank url
     *
     * @return string
     */
    public function getBankUrl()
    {
        return $this->bankUrl;
    }

    /**
     * Retrieve customer information
     *
     * @return string
     */
    public function getConsumerInfo()
    {
        return $this->consumerInfo;
    }

    /**
     * Set salt value for BW transaction
     *
     * @param unknown $salt
     */
    public function setSalt($salt)
    {
        $this->salt = $salt;
    }

    /**
     * *
     * Get BW more information
     *
     * @return \Digiwallet\Core\unknown
     */
    public function getMoreInformation()
    {
        return $this->moreInformation;
    }

    /**
     * Set country id value
     *
     * @param string $countryId
     * @return boolean
     */
    public function setCountryId($countryId)
    {
        $this->countryId = strtolower(substr($countryId, 0, 2));
        return true;
    }

    /**
     * Retrieve country id
     *
     * @return string
     */
    public function getCountryId()
    {
        return $this->countryId;
    }

    /**
     * Set currency value
     *
     * @param string $currency
     * @return boolean
     */
    public function setCurrency($currency)
    {
        $this->currency = strtoupper(substr($currency, 0, 3));
        return true;
    }

    /**
     * Retrieve currency
     *
     * @return string
     */
    public function getCurrency()
    {
        return $this->currency;
    }

    /**
     * Set description value
     *
     * @param string $description
     * @return boolean
     */
    public function setDescription($description)
    {
        $this->description = substr($description, 0, 32);
        return true;
    }

    /**
     * Retrieve description
     *
     * @return string
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * Get error message
     *
     * @return string
     */
    public function getErrorMessage()
    {
        $returnVal = '';
        if (! empty($this->errorMessage)) {
            if ($this->language == "nl" && strpos( $this->errorMessage, " | ") !== false) {
                list ($returnVal) = explode(" | ", $this->errorMessage, 2);
            } elseif ($this->language == "en" && strpos( $this->errorMessage, " | ") !== false) {
                list ($discard, $returnVal) = explode(" | ", $this->errorMessage, 2);
            } else {
                $returnVal = $this->errorMessage;
            }
        }
        return $returnVal;
    }

    /**
     * Get language
     *
     * @param boolean $allowList
     * @param boolean $defaultLanguage
     * @return string
     */
    public function getLanguage($allowList = false, $defaultLanguage = false)
    {
        if (! $allowList) {
            return $this->language;
        } else {
            if (in_array(strtoupper($this->language), $allowList)) {
                return strtoupper($this->language);
            } else {
                return $this->defaultLanguage;
            }
        }
    }

    /**
     * Retrieve payment paid status
     *
     * @return boolean
     */
    public function getPaidStatus()
    {
        return $this->paidStatus;
    }

    /**
     * Retrieve payment method
     *
     * @return string
     */
    public function getPayMethod()
    {
        return $this->payMethod;
    }

    /**
     * Set report URL value
     *
     * @param string $reportUrl
     *            Report URL
     * @return boolean
     */
    public function setReportUrl($reportUrl)
    {
        if (preg_match('|(\w+)://([^/:]+)(:\d+)?(.*)|', $reportUrl)) {
            $this->reportUrl = $reportUrl;
            return true;
        } else {
            return false;
        }
    }

    /**
     * Retrieve report URL
     *
     * @return string
     */
    public function getReportUrl()
    {
        return $this->reportUrl;
    }

    /**
     * Set return URL value
     *
     * @param string $returnUrl
     *            Return URL
     * @return boolean
     */
    public function setReturnUrl($returnUrl)
    {
        if (preg_match('|(\w+)://([^/:]+)(:\d+)?(.*)|', $returnUrl)) {
            $this->returnUrl = $returnUrl;
            return true;
        } else {
            return false;
        }
    }

    /**
     * Retrieve return URL
     *
     * @return string
     */
    public function getReturnUrl()
    {
        return $this->returnUrl;
    }

    /**
     * Set cancel URL value
     *
     * @param string $cancelUrl
     *            Cancel URL
     * @return boolean
     */
    public function setCancelUrl($cancelUrl)
    {
        if (preg_match('|(\w+)://([^/:]+)(:\d+)?(.*)|', $cancelUrl)) {
            $this->cancelUrl = $cancelUrl;
            return true;
        } else {
            return false;
        }
    }

    /**
     * Retrieve cancel URL
     *
     * @return string
     */
    public function getCancelUrl()
    {
        return $this->cancelUrl;
    }

    /**
     * Set transaction id
     *
     * @param string $transactionId
     * @return boolean
     */
    public function setTransactionId($transactionId)
    {
        $this->transactionId = substr($transactionId, 0, 32);
        return true;
    }

    /**
     * Retrieve transaction id
     *
     * @return string
     */
    public function getTransactionId()
    {
        return $this->transactionId;
    }
}
