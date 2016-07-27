# iDEAL plugin for Magento2 (Beta)

## Usage
Use this plugin to add support for iDEAL, Mister Cash, Sofort and other payment methods of 
TargetPay.com to your Magento2 webstore. 

## Installation

### 1. Set up a TargetPay account
Before you can use the plugin, please sign up for a TargetPay account on www.targetpay.com

Use our promotional code YM3R2A for a special discount on the transaction costs. 
Currently iDEAL is offered for 44 eurocent per transaction, all inclusive without monthly or setup fees.

Note that the plugin can be used in a live environment only after it has been completed with your details and
is approved by their compliance department. This would normally take about one working day.

### 2. Download or clone this repository

We recommend cloning the repository so you can easily get updates. 

### 3. Setting up

	How to setup:

	1. Extract the attachment zip into app/code

	The path will be: app/code/Targetpay/Ideal or app/code/Targetpay/Mrcash, ...

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

	Newly installed methods will be there
	Enable them and check/uncheck test mode for testing

	More detailed installation instruction will be available soon on https://www.idealplugins.nl/magento2#tab_install

### 4. Troubleshooting

Please see the FAQ on https://www.idealplugins.nl/magento2#tab_help
