<?php
namespace Targetpay\Sofort\Model;

class SofortConfigProvider implements \Magento\Checkout\Model\ConfigProviderInterface
{
    /**
     * @var string
     */
    private $methodCode = \Targetpay\Sofort\Model\Sofort::METHOD_CODE;

    /**
     * @var \Targetpay\Sofort\Model\Sofort
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
                'sofort' => [
                    'countries' => $this->getCountries(),
                    'redirectUrl' => $this->urlBuilder->getUrl('sofort/sofort/redirect', ['_secure' => true]),
                ],
            ],
        ] : [];
    }

    /**
     * Get bank list
     *
     * @return array
     */
    private function getCountries()
    {
        return $this->method->getCountryList();
    }
}
