<?php

declare(strict_types = 1);

namespace Drupal\commerce_paytrail\RequestBuilder;

use Drupal\commerce_order\Entity\OrderInterface;
use Paytrail\Payment\Model\Payment;
use Paytrail\Payment\Model\PaymentRequest;
use Paytrail\Payment\Model\PaymentRequestResponse;

/**
 * Payment request builder interface.
 */
interface PaymentRequestBuilderInterface extends RequestBuilderInterface {

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
   * @return \Paytrail\Payment\Model\Payment
   *   The payment.
   */
  public function get(string $transactionId, OrderInterface $order) : Payment;

  /**
   * Creates a new payment request.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order.
   *
   * @return \Paytrail\Payment\Model\PaymentRequestResponse
   *   The payment request response.
   *
   * @throws \Paytrail\Payment\ApiException
   */
  public function create(OrderInterface $order) : PaymentRequestResponse;

  /**
   * Creates a new payment request object.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order.
   *
   * @return \Paytrail\Payment\Model\PaymentRequest
   *   The payment request.
   *
   * @throws \Paytrail\Payment\ApiException
   */
  public function createPaymentRequest(OrderInterface $order) : PaymentRequest;

}
