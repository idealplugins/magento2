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
                template: 'Digiwallet_Bancontact/payment/form',
                redirectAfterPlaceOrder: false //Compatible with CE 2.1.0
            },

            getCode: function () {
                return 'bancontact';
            },

            isActive: function () {
                return true;
            },

            afterPlaceOrder: function () {
                window.location.replace(url.build('bancontact/bancontact/redirect?_secure=true'));
            },

            validate: function () {
                var $form = $('#' + this.getCode() + '-form');
                return $form.validation() && $form.validation('isValid');
            }
        });
    }
);