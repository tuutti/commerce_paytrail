<?php

declare(strict_types = 1);

namespace Drupal\commerce_paytrail\RequestBuilder;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_price\Price;
use Paytrail\SDK\Response\RefundResponse;

/**
 * Refund request builder interface.
 */
interface RefundRequestBuilderInterface {

  public const REFUND_CREATE_RESPONSE = 'create_refund_response';
  public const REFUND_CREATE = 'create_refund_request';

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
   * @return \Paytrail\SDK\Response\RefundResponse
   *   The refund response.
   */
  public function refund(string $transactionId, OrderInterface $order, Price $amount) : RefundResponse;

}
