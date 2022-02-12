<?php

declare(strict_types = 1);

namespace Drupal\commerce_paytrail\RequestBuilder;

use Drupal\commerce_order\Entity\OrderInterface;
use Paytrail\Payment\ApiException;

/**
 * A trait to provide a way to read/write transaction ids.
 */
trait TransactionIdTrait {

  /**
   * Gets the stored transaction id for given order.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   Gets the stored transaction ID for order.
   *
   * @return string
   *   The transaction ID.
   *
   * @throws \Paytrail\Payment\ApiException
   */
  protected function getTransactionId(OrderInterface $order) : string {
    if (!$transactionId = $order->getData('commerce_paytrail_transaction_id')) {
      throw new ApiException('No transaction id found for: ' . $order->id());
    }
    return $transactionId;
  }

  /**
   * Sets the transaction id.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order.
   * @param string $transactionId
   *   The transaction id.
   *
   * @return $this
   *   The self.
   */
  protected function setTransactionId(OrderInterface $order, string $transactionId) : self {
    $order->setData('commerce_paytrail_transaction_id', $transactionId);
    return $this;
  }

}
