# Commerce paytrail
[![Build Status](https://gitlab.com/tuutti/commerce_paytrail/badges/8.x-1.x/pipeline.svg)](https://gitlab.com/tuutti/commerce_paytrail)

## Description
This module integrates [Paytrail](https://www.paytrail.com/en) payment method with Drupal Commerce.

## Installation
`$ composer require drupal/commerce_paytrail`

## Usage

#### How to alter values submitted to the Paytrail

Create new event subscriber that responds to \Drupal\commerce_paytrail\Events\PaytrailEvents::FORM_ALTER.
