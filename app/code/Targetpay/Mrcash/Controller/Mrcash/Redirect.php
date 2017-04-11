<?php
namespace Targetpay\Mrcash\Controller\Mrcash;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Controller\ResultFactory;

/**
 * Targetpay Mrcash Redirect Controller
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
     * @var \Targetpay\Mrcash\Model\Mrcash
     */
    private $mrcash;

    /**
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param \Psr\Log\LoggerInterface $logger
     * @param \Targetpay\Mrcash\Model\Mrcash $mrcash
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Psr\Log\LoggerInterface $logger,
        \Targetpay\Mrcash\Model\Mrcash $mrcash
    ) {
        parent::__construct($context);
        $this->checkoutSession = $checkoutSession;
        $this->logger = $logger;
        $this->mrcash = $mrcash;
    }

    /**
     * When a customer has ordered and redirect to Targetpay Mrcash gateway.
     *
     * @return void|\Magento\Framework\Controller\Result\Redirect
     */
    public function execute()
    {
        /* @var \Magento\Framework\Controller\Result\Redirect $resultRedirect */
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);

        try {
            $mrcashUrl = $this->mrcash->setupPayment();
            $this->_redirect($mrcashUrl);
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
