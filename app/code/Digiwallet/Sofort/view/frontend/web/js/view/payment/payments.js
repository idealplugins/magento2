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
                type: 'sofort',
                component: 'Digiwallet_Sofort/js/view/payment/method-renderer/method'
            }
        );
        /** Add view logic here if needed */
        return Component.extend({});
    }
);