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
                template: 'Targetpay_Ideal/payment/form',
                selectedBank: null
            },

            getCode: function () {
                return 'ideal';
            },

            getData: function () {
                return {
                    'method': this.item.method,
                    'additional_data': {
                        'bank_id': this.selectedBank
                    }
                }
            },

            isActive: function () {
                return true;
            },

            afterPlaceOrder: function () {
                window.location.replace(url.build('ideal/ideal/redirect?_secure=true&bank_id=' + this.selectedBank));
            },

            /**
             * @returns {Array}
             */
            getBanks: function () {
                var banks = window.checkoutConfig.payment.ideal.banks,
                    filteredBanks = [];

                for (var key in banks) {
                    filteredBanks.push({id: key, name: banks[key]});
                }

                return filteredBanks;
            },

            selectedBank: ko.observable(),

            validate: function () {
                var $form = $('#' + this.getCode() + '-form');
                return $form.validation() && $form.validation('isValid');
            }
        });
    }
);
