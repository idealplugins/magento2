/*browser:true*/
/*global define*/
define(
    [
        'uiComponent',
        'Magento_Checkout/js/model/payment/renderer-list'
    ],
    function (
        Component,
        rendererList
    ) {
        'use strict';
        rendererList.push(
            {
                type: 'mrcash',
                component: 'Targetpay_Mrcash/js/view/payment/method-renderer/method'
            }
        );
        /** Add view logic here if needed */
        return Component.extend({});
    }
);