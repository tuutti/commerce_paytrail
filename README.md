# Commerce paytrail

[![Build Status](https://gitlab.com/tuutti/commerce_paytrail/badges/8.x-2.x/pipeline.svg)](https://gitlab.com/tuutti/commerce_paytrail)

## Description

This module integrates [Paytrail](https://www.paytrail.com/en) payment method with Drupal Commerce.

 * For a full description of the module, visit the project page:
   https://www.drupal.org/project/commerce_paytrail

 * To submit bug reports and feature suggestions, or to track changes:
   https://www.drupal.org/project/issues/commerce_paytrail

## Installation

Install the Commerce paytrail module as you would normally install a contributed
Drupal module. Visit https://www.drupal.org/node/1897420 for further
information.

## Configuration

1. Configure the Commerce Paytrail gateway from the Administration > Commerce >
   Configuration > Payment Gateways (`/admin/commerce/config/payment-gateways`),
   by editing an existing or adding a new payment gateway.
2. Select 'Paytrail' for the payment gateway plugin. Paytrail-specific fields
   will appear in the settings.

   * `Mode`: enables the Paytrail payment gateway in test or live mode.
   * `Merchant ID`: provide your Paytrail Merchant ID.
   * `Collect product details`: confirm whether to send product details to
      Paytrail.
   * `Allow IPN to create new payments`: allows Paytrail to automatically
      create a new payment in case user never returns from the payment gateway.
   * `Bypass Paytrail's payment method selection page`: redirects users
      directly to the selected payment service.
   * `Visible Payment methods`: configure the approved payment methods shown
      on the payment page. If left empty, all available payment methods shown.

3. Click Save to save your configuration.

## Maintainers

* tuutti (tuutti) - https://www.drupal.org/u/tuutti
