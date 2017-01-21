<?php

namespace Drupal\commerce_paytrail\Plugin\Commerce\PaymentGateway;

use Drupal\commerce_payment\Entity\PaymentMethodInterface;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\SupportsStoredPaymentMethodsInterface;

/**
 * Provides the PaytrailBase payment gateway.
 *
 * @todo Editing payment details is broken at the moment due to not having support
 * for offsite payment.
 *
 * @CommercePaymentGateway(
 *   id = "paytrail_bypass_mode",
 *   label = "PaytrailBase (Payment page bypass)",
 *   display_label = "PaytrailBase",
 *   payment_method_types = {"paytrail"},
 *   forms = {
 *     "add-payment-method" = "Drupal\commerce_paytrail\PluginForm\PaytrailBase\PaymentMethodAddForm",
 *     "edit-payment-method" = "Drupal\commerce_paytrail\PluginForm\PaytrailBase\PaymentMethodEditForm",
 *     "offsite-payment" = "Drupal\commerce_paytrail\PluginForm\OffsiteRedirect\PaytrailOffsiteForm",
 *   },
 * )
 */
class PaytrailBypassMode extends PaytrailBase implements SupportsStoredPaymentMethodsInterface {

  /**
   * {@inheritdoc}
   */
  public function createPaymentMethod(PaymentMethodInterface $payment_method, array $payment_details = NULL) {
    // Store preselected method if available.
    if (isset($payment_details['preselected_method'])) {
      $payment_method->set('preselected_method', $payment_details['preselected_method']);
    }
    $payment_method->setReusable(TRUE);
    $payment_method->save();
  }

  /**
   * {@inheritdoc}
   */
  public function deletePaymentMethod(PaymentMethodInterface $payment_method) {
    $payment_method->delete();
  }

}
