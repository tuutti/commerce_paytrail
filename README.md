# Commerce paytrail

![CI](https://github.com/tuutti/commerce_paytrail/workflows/CI/badge.svg)[![codecov](https://codecov.io/github/tuutti/commerce_paytrail/branch/8.x-1.x/graph/badge.svg?token=32zwdww9JR)](https://codecov.io/github/tuutti/commerce_paytrail)

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

### Postal code validation

Paytrail doesn't support non-digit postal codes, so collecting billing information for countries like UK is not possible at the moment.

See:
- https://github.com/paytrail/api-documentation/issues/34.
- https://www.drupal.org/project/commerce_paytrail/issues/333547.

To mitigate this issue, you can either:

1. Disable the `Collect billing information` setting from Payment gateway settings to completely disable billing information form.
2. Remove `commerce_paytrail.billing_information_collector` service. This prevents Drupal from sending the payment information to Paytrail, but the payment information is still collected to Drupal.

The service can be removed by calling `$container->removeDefinition('commerce_paytrail.billing_information_collector')` in your service provider. See https://www.drupal.org/docs/drupal-apis/services-and-dependency-injection/altering-existing-services-providing-dynamic-services for more information.

## Maintainers

* tuutti (tuutti) - https://www.drupal.org/u/tuutti
