<?php

namespace Drupal\commerce_paytrail\Plugin\Commerce\PaymentMethodType;

use Drupal\commerce\BundleFieldDefinition;
use Drupal\commerce_payment\Entity\PaymentMethodInterface;
use Drupal\commerce_payment\Plugin\Commerce\PaymentMethodType\PaymentMethodTypeBase;

/**
 * Provides the credit card payment method type.
 *
 * @CommercePaymentMethodType(
 *   id = "paytrail",
 *   label = @Translation("PaytrailBase"),
 *   create_label = @Translation("PaytrailBase"),
 * )
 */
class Paytrail extends PaymentMethodTypeBase {

  /**
   * {@inheritdoc}
   */
  public function buildLabel(PaymentMethodInterface $payment_method) {
    if ($payment_method->isNew()) {
      return $this->t('PaytrailBase');
    }
    return $this->t('Previously submitted PaytrailBase');
  }

  /**
   * {@inheritdoc}
   */
  public function buildFieldDefinitions() {
    $fields = parent::buildFieldDefinitions();

    $fields['preselected_method'] = BundleFieldDefinition::create('integer')
      ->setLabel(t('Preselected method'))
      ->setDescription(t('The preselected payment method'))
      ->setSetting('size', 'tiny');

    return $fields;
  }

}
