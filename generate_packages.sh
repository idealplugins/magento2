#!/bin/bash
cd /media/Websites/idealPlugins/magento2/app/code/TargetPay/Core/ && rm -f targetpay-core-0.1.*.zip && zip -r targetpay-core-0.1.2.zip ./*
cd /media/Websites/idealPlugins/magento2/app/code/TargetPay/Creditcard/ && rm -f targetpay-creditcard-0.1.*.zip && zip -r targetpay-creditcard-0.1.2.zip ./*
cd /media/Websites/idealPlugins/magento2/app/code/TargetPay/Ideal/ && rm -f targetpay-ideal-0.1.*.zip && zip -r targetpay-ideal-0.1.2.zip ./*
cd /media/Websites/idealPlugins/magento2/app/code/TargetPay/Mrcash/ && rm -f targetpay-mrcash-0.1.*.zip && zip -r targetpay-mrcash-0.1.2.zip ./*
cd /media/Websites/idealPlugins/magento2/app/code/TargetPay/Paysafecard/ && rm -f targetpay-paysafecard-0.1.*.zip && zip -r targetpay-paysafecard-0.1.2.zip ./*
cd /media/Websites/idealPlugins/magento2/app/code/TargetPay/Sofort/ && rm -f targetpay-sofort-0.1.*.zip && zip -r targetpay-sofort-0.1.2.zip ./*
