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
                template: 'Targetpay_Paysafecard/payment/form',
                redirectAfterPlaceOrder: false //Fix compatible with CE 2.1.0
            },

            getCode: function () {
                return 'paysafecard';
            },

            isActive: function () {
                return true;
            },

            afterPlaceOrder: function () {
                window.location.replace(url.build('paysafecard/paysafecard/redirect?_secure=true'));
            },

            validate: function () {
                var $form = $('#' + this.getCode() + '-form');
                return $form.validation() && $form.validation('isValid');
            }
        });
    }
);
