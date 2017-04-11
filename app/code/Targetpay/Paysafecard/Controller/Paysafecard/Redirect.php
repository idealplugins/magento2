<?php
namespace Targetpay\Paysafecard\Controller\Paysafecard;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Controller\ResultFactory;

/**
 * Targetpay Paysafecard Redirect Controller
 *
 * @method GET
 */
class Redirect extends \Magento\Framework\App\Action\Action
{
    /**
     * @var \Magento\Checkout\Model\Session
     */
    private $checkoutSession;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    private $logger;

    /**
     * @var \Targetpay\Paysafecard\Model\Paysafecard
     */
    private $paysafecard;

    /**
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param \Psr\Log\LoggerInterface $logger
     * @param \Targetpay\Paysafecard\Model\Paysafecard $paysafecard
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Targetpay\Paysafecard\Model\Paysafecard $paysafecard,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Psr\Log\LoggerInterface $logger
    ) {
        parent::__construct($context);
        $this->checkoutSession = $checkoutSession;
        $this->logger = $logger;
        $this->paysafecard = $paysafecard;
    }

    /**
     * When a customer has ordered and redirect to Targetpay Paysafecard gateway.
     *
     * @return void|\Magento\Framework\Controller\Result\Redirect
     */
    public function execute()
    {
        /* @var \Magento\Framework\Controller\Result\Redirect $resultRedirect */
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);

        try {
            $paysafecardUrl = $this->paysafecard->setupPayment();
            $this->_redirect($paysafecardUrl);
            return;
        } catch (LocalizedException $e) {
            $this->messageManager->addExceptionMessage(
                $e,
                $e->getMessage()
            );
        } catch (\Exception $e) {
            $this->messageManager->addExceptionMessage(
                $e,
                __('Something went wrong, please try again later')
            );
            $this->logger->critical($e);
        }
        $this->checkoutSession->restoreQuote();
        return $resultRedirect->setPath('checkout/cart');
    }
}
