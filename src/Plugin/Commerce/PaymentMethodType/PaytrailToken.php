<?php

declare(strict_types = 1);

namespace Drupal\commerce_paytrail\Plugin\Commerce\PaymentMethodType;

use Drupal\commerce_payment\Entity\PaymentMethodInterface;
use Drupal\commerce_payment\Plugin\Commerce\PaymentMethodType\CreditCard;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Provides the Paytrail payment method type.
 *
 * @CommercePaymentMethodType(
 *   id = "paytrail_token",
 *   label = @Translation("Paytrail (Credit card)"),
 *   create_label = @Translation("Paytrail (Credit card)"),
 * )
 */
final class PaytrailToken extends CreditCard {

  /**
   * {@inheritdoc}
   */
  public function buildLabel(PaymentMethodInterface $payment_method) : TranslatableMarkup {
    if (isset($payment_method->card_type->value)) {
      return parent::buildLabel($payment_method);
    }

    return $this->t('Paytrail (Credit card)');
  }

}
