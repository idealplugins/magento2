<?php
namespace Targetpay\Sofort\Controller\Sofort;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Controller\ResultFactory;

/**
 * Targetpay Sofort Redirect Controller
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
     * @var \Targetpay\Sofort\Model\Sofort
     */
    private $sofort;

    /**
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param \Psr\Log\LoggerInterface $logger
     * @param \Targetpay\Sofort\Model\Sofort $sofort
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Targetpay\Sofort\Model\Sofort $sofort,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Psr\Log\LoggerInterface $logger
    ) {
        parent::__construct($context);
        $this->checkoutSession = $checkoutSession;
        $this->logger = $logger;
        $this->sofort = $sofort;
    }

    /**
     * When a customer has ordered and redirect to Targetpay Sofort gateway.
     *
     * @return void|\Magento\Framework\Controller\Result\Redirect
     */
    public function execute()
    {
        /* @var \Magento\Framework\Controller\Result\Redirect $resultRedirect */
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        try {
            $countryId = $this->getRequest()->getParam('country_id');
            $sofortUrl = $this->sofort->setupPayment($countryId);
            $this->_redirect($sofortUrl);
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
