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
                template: 'Targetpay_Sofort/payment/form',
                selectedCountry: null
            },

            getCode: function () {
                return 'sofort';
            },

            getData: function () {
                return {
                    'method': this.item.method,
                    'additional_data': {
                        'country_id': this.selectedCountry
                    }
                }
            },

            isActive: function () {
                return true;
            },

            afterPlaceOrder: function () {
                window.location.replace(url.build('sofort/sofort/redirect?_secure=true&country_id=' + this.selectedCountry));
            },

            /**
             * @returns {Array}
             */
            getCountries: function () {
                var countries = window.checkoutConfig.payment.sofort.countries,
                    filteredCountries = [];

                for (var key in countries) {
                    filteredCountries.push({id: key, name: countries[key]});
                }

                return filteredCountries;
            },

            selectedCountry: ko.observable(),

            validate: function () {
                var $form = $('#' + this.getCode() + '-form');
                return $form.validation() && $form.validation('isValid');
            }
        });
    }
);
