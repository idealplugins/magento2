<?php
namespace Targetpay\Paysafecard\Model;

class PaysafecardConfigProvider implements \Magento\Checkout\Model\ConfigProviderInterface
{

    /**
     * @var string
     */
    protected $methodCode = \Targetpay\Paysafecard\Model\Paysafecard::METHOD_CODE;
    /**
     * @var \Targetpay\Paysafecard\Model\Paysafecard
     */
    protected $method;
    /**
     * @var \Magento\Framework\Escaper
     */
    protected $escaper;
    /**
     * @var \Magento\Framework\UrlInterface
     */
    protected $urlBuilder;

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
                'paysafecard' => [
                    'redirectUrl' => $this->urlBuilder->getUrl('paysafecard/paysafecard/redirect', ['_secure' => true]),
                ],
            ],
        ] : [];
    }
}
