<?php

declare(strict_types = 1);

namespace Drupal\commerce_paytrail\RequestBuilder;

use Drupal\commerce_order\Entity\OrderInterface;
use Paytrail\SDK\Response\PaymentResponse;
use Paytrail\SDK\Response\PaymentStatusResponse;

/**
 * Payment request builder interface.
 */
interface PaymentRequestBuilderInterface {

  public const PAYMENT_GET_RESPONSE_EVENT = 'payment_get_response';
  public const PAYMENT_CREATE_EVENT = 'payment_create';
  public const PAYMENT_CREATE_RESPONSE_EVENT = 'payment_create_response';

  /**
   * Gets the payment for given order.
   *
   * @param string $transactionId
   *   The transaction ID.
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order.
   *
   * @return \Paytrail\SDK\Response\PaymentStatusResponse
   *   The payment.
   */
  public function get(string $transactionId, OrderInterface $order) : PaymentStatusResponse;

  /**
   * Creates a new payment request.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order.
   *
   * @return \Paytrail\SDK\Response\PaymentResponse
   *   The payment request response.
   *
   * @throws \Paytrail\Payment\ApiException
   */
  public function create(OrderInterface $order) : PaymentResponse;

}
