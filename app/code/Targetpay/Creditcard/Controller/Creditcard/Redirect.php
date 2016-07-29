<?php
namespace Targetpay\Creditcard\Controller\Creditcard;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Controller\ResultFactory;

/**
 * Targetpay Creditcard Redirect Controller
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
     * @var \Targetpay\Creditcard\Model\Creditcard
     */
    private $creditcard;

    /**
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Targetpay\Creditcard\Model\Creditcard $creditcard
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param \Psr\Log\LoggerInterface $logger
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Targetpay\Creditcard\Model\Creditcard $creditcard,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Psr\Log\LoggerInterface $logger
    ) {
        parent::__construct($context);
        $this->checkoutSession = $checkoutSession;
        $this->logger = $logger;
        $this->creditcard = $creditcard;
    }

    /**
     * When a customer has ordered and redirect to Targetpay Creditcard gateway.
     *
     * @return void|\Magento\Framework\Controller\Result\Redirect
     */
    public function execute()
    {
        /** @var \Magento\Framework\Controller\Result\Redirect $resultRedirect */
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);

        try {
            $creditcardUrl = $this->creditcard->setupPayment();
            $this->_redirect($creditcardUrl);
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
