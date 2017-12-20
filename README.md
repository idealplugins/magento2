# iDEAL plugin for Magento 2.x

## Usage
Use this plugin to add support for iDEAL, Mister Cash, Sofort and other payment methods of 
TargetPay.com and Digiwallet.nl to your webstore. 

## Supported Paymethods
| Paymethod	|   supported	| 
|-------------	|---	|
| iDEAL	|:heavy_check_mark:	|
| Bancontact	|:heavy_check_mark:	|
| Creditcard	|:heavy_check_mark:	|
| Paysafecard	|:heavy_check_mark:	|
| Sofort	|:heavy_check_mark:	|
| Paypal	|:heavy_check_mark:	|
| Afterpay	|:heavy_check_mark:	|
| Bankwire	|:heavy_check_mark:	|
| Refund Creation	|:heavy_check_mark:	|
| Refund Deletion	|:heavy_check_mark:	|

## Installation

### 1. Set up a Digiwallet account
Before you can use the plugin, please sign up for a Digiwallet account on www.digiwallet.nl

Note that the plugin can be used in a live environment only after it has been completed with your details and
is approved by their compliance department. This would normally take about one working day. 

You can obtain a personalized version of the plugin on https://www.idealplugins.nl/

### 2. Download or clone this repository

We recommend cloning the repository so you can easily get updates. 

### 3. Setting up

NOTE: If you installed the old Targetpay plugin before, please make sure to remove this first before proceeding with this new plugin, since the old plugin is not backwards compatible with the previous version.


	How to setup:

	1. Extract the attachment zip into app/code

	The path will be: app/code/Digiwallet/Ideal or app/code/Digiwallet/Mrcash, ...

	2. Install new module using the following commands:

	```
	php bin/magento setup:upgrade
	php bin/magento setup:di:compile
	```

	3. Clean cache

	```
	php bin/magento cache:flush
	```

	4. Delete static cached if needed

	```
	rm -rf var/* pub/*
	```

	5. Enable payment methods in Magento admin

	Go to Store > Configuration > Sale > Payment methods

	The Newly installed methods will be there
	Enable them and check/uncheck test mode for testing

More detailed installation instruction are avialable on https://www.idealplugins.nl/magento2#tab_install

## Troubleshooting

Please see the FAQ on https://www.idealplugins.nl/magento2#tab_help

## Magento 1.x

If you are using Magento 1.x, please find our plugin here: https://github.com/idealplugins/magento
