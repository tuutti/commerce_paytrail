<?php

declare(strict_types = 1);

namespace Drupal\commerce_paytrail\RequestBuilder;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_paytrail\Plugin\Commerce\PaymentGateway\Paytrail;
use Paytrail\Payment\Model\Payment;
use Paytrail\Payment\Model\PaymentRequest;
use Paytrail\Payment\Model\PaymentRequestResponse;

/**
 * Payment request builder interface.
 */
interface PaymentRequestBuilderInterface extends RequestBuilderInterface {

  /**
   * Gets the payment for given order.
   *
   * @param string $transactionId
   *   The transaction ID.
   * @param \Drupal\commerce_paytrail\Plugin\Commerce\PaymentGateway\Paytrail $plugin
   *   The payment gateway plugin.
   *
   * @return \Paytrail\Payment\Model\Payment
   *   The payment.
   */
  public function get(string $transactionId, Paytrail $plugin) : Payment;

  /**
   * Creates a new payment request.
   *
   * @param \Drupal\commerce_paytrail\Plugin\Commerce\PaymentGateway\Paytrail $plugin
   *   The payment gateway plugin.
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order.
   *
   * @return \Paytrail\Payment\Model\PaymentRequestResponse
   *   The payment request response.
   *
   * @throws \Paytrail\Payment\ApiException
   */
  public function create(Paytrail $plugin, OrderInterface $order) : PaymentRequestResponse;

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
