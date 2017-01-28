<?php

namespace Drupal\commerce_paytrail\Repository;

use Drupal\commerce_price\Price;

/**
 * Class S1TransactionRepository.
 *
 * @package Drupal\commerce_paytrail\Repository
 */
class S1TransactionRepository extends TransactionRepository {

  /**
   * {@inheritdoc}
   */
  protected function getKeys() {
    return ['amount' => ['#weight' => 1, '#required' => TRUE]] + parent::getKeys();
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

    return $this->set('amount', $formatted);
  }

  /**
   * {@inheritdoc}
   */
  protected function getType() {
    return 'S1';
  }

}
