<?php

namespace Drupal\commerce_paytrail\Plugin\Commerce\PaymentGateway;

/**
 * Provides the PaytrailBase payment gateway.
 *
 * @CommercePaymentGateway(
 *   id = "paytrail",
 *   label = "Paytrail",
 *   display_label = "Paytrail",
 *   payment_method_types = {"paytrail"},
 *   forms = {
 *     "offsite-payment" = "Drupal\commerce_paytrail\PluginForm\OffsiteRedirect\PaytrailOffsiteForm",
 *   },
 * )
 */
class Paytrail extends PaytrailBase {
}
