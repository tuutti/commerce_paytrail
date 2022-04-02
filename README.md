# Commerce paytrail

![CI](https://github.com/tuutti/commerce_paytrail/workflows/CI/badge.svg)

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
   * `Account`: provide your Paytrail account.
   * `Secret`: provide your Paytrail secret
3. Click Save to save your configuration.

## Known issues
Paytrail module provides a notification callback that will be called by Paytrail when the order is paid in full. This is used to make sure orders are captured (by Drupal) even if the customer never returns to Drupal from the payment gateway.

Paytrail however, will fire the callback as soon as the order is paid, often leading to a situation where `onNotify()` and `onReturn()` callbacks are called at the same time, causing a race-condition between the two. See [https://www.drupal.org/project/commerce/issues/3043180](https://www.drupal.org/project/commerce/issues/3043180) for more information about this.

To mitigate this issue, the order will be placed in a queue when the notification callback is fired and the queue will then be processed by cron.

See [#3268851](https://www.drupal.org/project/commerce_paytrail/issues/3268851).

## Maintainers

* tuutti (tuutti) - https://www.drupal.org/u/tuutti
