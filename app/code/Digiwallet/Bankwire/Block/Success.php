<?php
/**
 * Copyright © 2013-2017 Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Digiwallet\Bankwire\Block;

use Magento\Customer\Model\Context;
use Magento\Sales\Model\Order;
use Digiwallet\Bankwire\Model\Bankwire;

/**
 * One page checkout success page
 */
class Success extends \Magento\Framework\View\Element\Template
{
    /**
     * @var \Magento\Framework\App\ResourceConnection
     */
    private $resoureConnection;
    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $_checkoutSession;

    /**
     * @var \Magento\Sales\Model\Order\Config
     */
    protected $_orderConfig;

    /**
     * @var \Magento\Framework\App\Http\Context
     */
    protected $httpContext;
    
    private $orderRepository;

    /**
     * @param \Magento\Framework\View\Element\Template\Context $context
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param \Magento\Sales\Model\Order\Config $orderConfig
     * @param \Magento\Framework\App\Http\Context $httpContext
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Framework\App\ResourceConnection $resourceConnection,
        \Magento\Sales\Model\Order\Config $orderConfig,
        \Magento\Sales\Model\OrderRepository $orderRepository,
        \Magento\Framework\App\Http\Context $httpContext,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->_checkoutSession = $checkoutSession;
        $this->resoureConnection = $resourceConnection;
        $this->_orderConfig = $orderConfig;
        $this->_isScopePrivate = true;
        $this->httpContext = $httpContext;
        $this->orderRepository = $orderRepository;
    }

    /**
     * Initialize data and prepare it for output
     *
     * @return string
     */
    protected function _beforeToHtml()
    {
        $this->prepareBlockData();
        return parent::_beforeToHtml();
    }

    /**
     * Prepares block data
     *
     * @return void
     */
    protected function prepareBlockData()
    {
        $trans_id = $this->getRequest()->getParam('trxid');
        $db = $this->resoureConnection->getConnection();
        $tableName   = $this->resoureConnection->getTableName('digiwallet_transaction');
        $sql = "SELECT * FROM ".$tableName."
                WHERE `digi_txid` = " . $db->quote($trans_id) . "
                AND method=" . $db->quote(Bankwire::METHOD_TYPE);
        $result = $db->fetchAll($sql);  
        
        if (count($result)) {
            list($trxid, $accountNumber, $iban, $bic, $beneficiary, $bank) = explode("|", $result[0]['more']);
            $order = $this->orderRepository->get($result[0]['order_id']);      
            $this->addData(
                [
                    'trxid' => $trxid,
                    'account_number' => $accountNumber,
                    'iban' => $iban,
                    'bic' => $bic,
                    'beneficiary' => $beneficiary,
                    'bank' => $bank,
                    'order' => $order,
                    'email' => $this->hideCustomerEmail($order->getCustomerEmail())
               ]
           );
        }
    }
   /**
    * Change customer email to format: e.g.(s******@***.com)
    * 
    * @param string $email
    * @return string
    */
    private function hideCustomerEmail($email = "")
    {
        $email = str_split($email);
        $counter = 0;
        $result = "";
        foreach ($email as $char) {
            if($counter == 0) {
                $result .= $char;
                $counter++;
            } else if($char == "@") {
                $result .= $char;
                $counter++;
            } else if($char == "." && $counter > 1) {
                $result .= $char;
                $counter++;
            } else if($counter > 2) {
                $result .= $char;
            } else {
                $result .= "*";
            }
        }
        return $result;
    }
}
