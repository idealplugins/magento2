<?php
namespace Digiwallet\Afterpay\Model;

class AfterpayConfigProvider implements \Magento\Checkout\Model\ConfigProviderInterface
{

    /**
     *
     * @var string
     */
    private $methodCode = \Digiwallet\Afterpay\Model\Afterpay::METHOD_CODE;

    /**
     *
     * @var \Digiwallet\Afterpay\Model\Afterpay
     */
    private $method;

    /**
     *
     * @var \Magento\Framework\Escaper
     */
    private $escaper;

    /**
     *
     * @var \Magento\Framework\UrlInterface
     */
    private $urlBuilder;

    /**
     *
     * @param \Magento\Framework\Escaper $escaper            
     * @param \Magento\Payment\Helper\Data $paymentHelper            
     * @param \Magento\Framework\UrlInterface $urlBuilder
     *            @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        \Magento\Framework\Escaper $escaper, 
        \Magento\Payment\Helper\Data $paymentHelper, 
        \Magento\Framework\UrlInterface $urlBuilder)
    {
        $this->escaper = $escaper;
        $this->method = $paymentHelper->getMethodInstance($this->methodCode);
        $this->urlBuilder = $urlBuilder;
    }

    /**
     *
     * {@inheritdoc}
     *
     */
    public function getConfig()
    {
        return $this->method->isAvailable() ? [
            'payment' => [
                'afterpay' => [
                    'redirectUrl' => $this->urlBuilder->getUrl('afterpay/afterpay/redirect', [
                        '_secure' => true
                    ])
                ]
            ]
        ] : [];
    }
}
