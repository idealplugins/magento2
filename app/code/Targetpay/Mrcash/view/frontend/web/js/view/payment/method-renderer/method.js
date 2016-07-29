/*browser:true*/
/*global define*/
define(
    [
         'ko',
        'jquery',
        'Magento_Checkout/js/view/payment/default',
        'mage/url'
    ],
    function (ko, $, Component, url) {
        'use strict';

        return Component.extend({
            defaults: {
                template: 'Targetpay_Mrcash/payment/form',
                redirectAfterPlaceOrder: false //Compatible with CE 2.1.0
            },

            getCode: function () {
                return 'mrcash';
            },

            isActive: function () {
                return true;
            },

            afterPlaceOrder: function () {
                window.location.replace(url.build('mrcash/mrcash/redirect?_secure=true'));
            },

            validate: function () {
                var $form = $('#' + this.getCode() + '-form');
                return $form.validation() && $form.validation('isValid');
            }
        });
    }
);
