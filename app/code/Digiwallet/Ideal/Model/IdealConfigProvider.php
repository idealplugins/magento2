<?php
namespace Digiwallet\Ideal\Model;

class IdealConfigProvider implements \Magento\Checkout\Model\ConfigProviderInterface
{

    /**
     *
     * @var string
     */
    private $methodCode = \Digiwallet\Ideal\Model\Ideal::METHOD_CODE;

    /**
     *
     * @var \Digiwallet\Ideal\Model\Ideal
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
                'ideal' => [
                    'redirectUrl' => $this->urlBuilder->getUrl('ideal/ideal/redirect', [
                        '_secure' => true
                    ])
                ]
            ]
        ] : [];
    }
}
