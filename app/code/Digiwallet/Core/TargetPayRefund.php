<?php 
namespace Digiwallet\Core;

use Digiwallet\Core\TargetPayCore;

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
 * @class Digiwallet Core class
 */
class TargetPayRefund
{
    /**
     * Payment method ID
     * @var unknown
     */
    private $methodId;
    /**
     * Payment Infterface object
     * 
     * @var \Magento\Payment\Model\InfoInterface
     */
    private $payment;
    /**
     * 
     * @var integer
     */
    private $amount;
    /**
     * @var \Magento\Framework\App\ResourceConnection
     */
    private $resoureConnection;
    /**
     * @var \Magento\Framework\Locale\Resolver
     */
    private $localeResolver;
    /**
     * Token
     * @var string
     */
    private $apiToken;
    /**
     * Test mode
     * @var boolean
     */
    private $isTestMode;
    /**
     * Language
     * @var unknown
     */
    private $language;
    
    /**
     * RTLO code
     * @var unknown
     */
    private $rtloCode;
    
    /**
     * Constructor
     * @param unknown $paymentMethodId
     * @param unknown $amount
     * @param unknown $api_token
     * @param \Magento\Payment\Model\InfoInterface $payment
     * @param \Magento\Framework\App\ResourceConnection $resoureConnection
     */
    public function __construct(
        $paymentMethodId, 
        $amount,
        $api_token,
        \Magento\Payment\Model\InfoInterface $payment, 
        \Magento\Framework\App\ResourceConnection $resoureConnection
    )
    {
        $this->methodId = $paymentMethodId;
        $this->amount = $amount;
        $this->payment = $payment;
        $this->resoureConnection = $resoureConnection;
        $this->apiToken = $api_token;
        $this->rtloCode = '';
        $this->isTestMode = false;
        $this->language = 'nl';
    }
    /**
     * Set testmode
     * @param unknown $testMode
     */
    public function setTestMode($testMode)
    {
        $this->isTestMode = $testMode;
    }
    /**
     * Set language
     * @param unknown $lang
     */
    public function setLanguage($lang)
    {
        $this->language = $lang;
    }
    /**
     * Set Layout code
     * @param unknown $rtlo
     */
    public function setLayoutCode($rtlo)
    {
        $this->rtloCode = $rtlo;
    }
    /**
     * Refund transaction
     */
    public function refund()
    {
        /** @var \Magento\Sales\Model\Order $order */
        $order = $this->payment->getOrder();
        $db = $this->resoureConnection->getConnection();
        $tableName   = $this->resoureConnection->getTableName('digiwallet_transaction');
        $sql = "SELECT `digi_txid` FROM ".$tableName."
                WHERE `order_id` = " . $db->quote((int) $order->getIncrementId()) . "
                AND method=" . $db->quote($this->methodId) . " AND paid IS NOT NULL";
        $result = $db->fetchAll($sql);
        if (!count($result)) {
            throw new \Exception(__("Digiwallet refunding error: Payment transaction not found."));
            return;
        }
        $transactionId = $result[0]['digi_txid'];
        $description = isset($_REQUEST['creditmemo']) ? $_REQUEST['creditmemo']['comment_text'] : "";
        $internalNote =  "Refunding Order with orderId: " . $order->getIncrementId() . " - Digiwallet transactionId: $transactionId - Total price: " . $order->getBaseCurrency()->formatTxt($order->getGrandTotal());
        $consumerName = $order->getCustomerName();
        if($order->getCustomer() != null){
            $consumerName = $order->getCustomer()->getName();
        }
        
        $refundData = array(
            'paymethodID' => $this->methodId,
            'transactionID' => $transactionId,
            'amount' => (int) ($this->amount * 100), // Convert amount to int and value in cent
            'description' => $description,
            'internalNote' => $internalNote,
            'consumerName' => $consumerName
        );
        $digiCore = new TargetPayCore(
            $this->methodId,
            $this->rtloCode,
            $this->language,
            $this->isTestMode
            );
        // Refund sucess if testmode enable
        if(!$this->isTestMode && !$digiCore->refundInvoice($refundData, $this->apiToken)){
            throw new \Exception(__("Digiwallet refunding error: {$digiCore->getErrorMessage()}"));
        }
    }
}
