<?php

declare(strict_types = 1);

namespace Drupal\commerce_paytrail\Plugin\Commerce\PaymentMethodType;

use Drupal\commerce_payment\Entity\PaymentMethodInterface;
use Drupal\commerce_payment\Plugin\Commerce\PaymentMethodType\PaymentMethodTypeBase;

/**
 * Provides the Paytrail payment method type.
 *
 * @CommercePaymentMethodType(
 *   id = "paytrail",
 *   label = @Translation("Paytrail"),
 *   create_label = @Translation("Paytrail"),
 * )
 */
final class Paytrail extends PaymentMethodTypeBase {

  /**
   * {@inheritdoc}
   */
  public function buildLabel(PaymentMethodInterface $payment_method) : string {
    return (string) $this->t('Paytrail');
  }

}
