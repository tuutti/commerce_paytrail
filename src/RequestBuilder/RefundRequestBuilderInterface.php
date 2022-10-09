<?php

declare(strict_types = 1);

namespace Drupal\commerce_paytrail\RequestBuilder;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_price\Price;
use Paytrail\Payment\Model\Refund;
use Paytrail\Payment\Model\RefundResponse;

/**
 * Refund request builder interface.
 */
interface RefundRequestBuilderInterface extends RequestBuilderInterface {

  /**
   * Refunds the given order and amount.
   *
   * @param string $transactionId
   *   The transaction ID.
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order to refund.
   * @param \Drupal\commerce_price\Price $amount
   *   The amount.
   *
   * @return \Paytrail\Payment\Model\RefundResponse
   *   The refund response.
   */
  public function refund(string $transactionId, OrderInterface $order, Price $amount) : RefundResponse;

  /**
   * Creates a new refund request object.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order.
   * @param \Drupal\commerce_price\Price $amount
   *   The amount.
   * @param string $nonce
   *   The nonce.
   *
   * @return \Paytrail\Payment\Model\Refund
   *   The refund request model.
   */
  public function createRefundRequest(
    OrderInterface $order,
    Price $amount,
    string $nonce
  ) : Refund;

}
