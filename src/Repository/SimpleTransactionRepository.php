<?php

namespace Drupal\commerce_paytrail\Repository;

use Drupal\commerce_price\Price;

/**
 * Class SimpleTransactionRepository.
 *
 * @package Drupal\commerce_paytrail\Repository
 */
class SimpleTransactionRepository extends TransactionRepository {

  /**
   * {@inheritdoc}
   */
  protected function getKeys() {
    return ['amount' => ''] + parent::getKeys();
  }

  /**
   * Set amount.
   *
   * @param \Drupal\commerce_price\Price $price
   *   The amount.
   *
   * @return $this
   */
  public function setAmount(Price $price) {
    $formatted = number_format($price->getNumber(), 2, '.', '');

    return $this->set('amount', $formatted, [
      '#required' => TRUE,
      '#weight' => 1,
    ]);
  }

  /**
   * {@inheritdoc}
   */
  protected function getType() {
    return 'S1';
  }

}
