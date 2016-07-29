<?php
namespace Targetpay\Creditcard\Model;

class CreditcardConfigProvider implements \Magento\Checkout\Model\ConfigProviderInterface
{
    /**
     * @var string
     */
    private $methodCode = \Targetpay\Creditcard\Model\Creditcard::METHOD_CODE;

    /**
     * @var \Targetpay\Creditcard\Model\Creditcard
     */
    private $method;

    /**
     * @var \Magento\Framework\Escaper
     */
    private $escaper;

    /**
     * @var \Magento\Framework\UrlInterface
     */
    private $urlBuilder;

    /**
     * @param \Magento\Framework\Escaper $escaper
     * @param \Magento\Payment\Helper\Data $paymentHelper
     * @param \Magento\Framework\UrlInterface $urlBuilder
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        \Magento\Framework\Escaper $escaper,
        \Magento\Payment\Helper\Data $paymentHelper,
        \Magento\Framework\UrlInterface $urlBuilder
    ) {
        $this->escaper = $escaper;
        $this->method = $paymentHelper->getMethodInstance($this->methodCode);
        $this->urlBuilder = $urlBuilder;
    }

    /**
     * {@inheritdoc}
     */
    public function getConfig()
    {
        return $this->method->isAvailable() ? [
            'payment' => [
                'creditcard' => [
                    'redirectUrl' => $this->urlBuilder->getUrl('creditcard/creditcard/redirect', ['_secure' => true]),
                ],
            ],
        ] : [];
    }
}
