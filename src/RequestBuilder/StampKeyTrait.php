<?php

declare(strict_types = 1);

namespace Drupal\commerce_paytrail\RequestBuilder;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_paytrail\Exception\SecurityHashMismatchException;

/**
 * A trait to provide a way to read/write stamp keys.
 */
trait StampKeyTrait {

  /**
   * Stores the stamp in given order.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order.
   * @param string $stamp
   *   The stamp.
   *
   * @return $this
   *   The self.
   */
  protected function setStamp(OrderInterface $order, string $stamp) : self {
    $order->setData('commerce_paytrail_stamp', $stamp);
    return $this;
  }

  /**
   * Checks if returned stamp matches with stored one.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order to check.
   * @param string $expectedStamp
   *   The expected stamp.
   *
   * @return $this
   *   The self.
   *
   * @throws \Drupal\commerce_paytrail\Exception\SecurityHashMismatchException
   */
  public function validateStamp(OrderInterface $order, string $expectedStamp) : self {
    if ((!$stamp = $order->getData('commerce_paytrail_stamp')) || $stamp !== $expectedStamp) {
      throw new SecurityHashMismatchException('Stamp validation failed.');
    }
    return $this;
  }

}
