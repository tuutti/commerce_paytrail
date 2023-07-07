<?php

declare(strict_types = 1);

namespace Drupal\commerce_paytrail\RequestBuilder;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_paytrail\Event\ModelEvent;
use Paytrail\SDK\Request\PaymentRequest;
use Paytrail\SDK\Request\PaymentStatusRequest;
use Paytrail\SDK\Response\PaymentResponse;
use Paytrail\SDK\Response\PaymentStatusResponse;

/**
 * The payment request builder.
 *
 * @internal
 */
final class PaymentRequestBuilder extends PaymentRequestBase implements PaymentRequestBuilderInterface {

  /**
   * {@inheritdoc}
   */
  public function get(string $transactionId, OrderInterface $order) : PaymentStatusResponse {
    $plugin = $this->getPaymentPlugin($order);

    $request = (new PaymentStatusRequest())
      ->setTransactionId($transactionId);
    $request->setTransactionId($transactionId);

    $response = $plugin->getClient()
      ->getPaymentStatus($request);

    $this->eventDispatcher->dispatch(new ModelEvent(
      $response,
      $order,
      PaymentRequestBuilderInterface::PAYMENT_GET_RESPONSE_EVENT
    ));
    return $response;
  }

  /**
   * Constructs a new payment request.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order.
   *
   * @return \Paytrail\SDK\Request\PaymentRequest
   *   The request.
   */
  public function createPaymentRequest(OrderInterface $order) : PaymentRequest {
    return $this->populatePaymentRequest(
      new PaymentRequest(),
      $order,
      PaymentRequestBuilderInterface::PAYMENT_CREATE_EVENT,
    );
  }

  /**
   * {@inheritdoc}
   */
  public function create(OrderInterface $order) : PaymentResponse {
    $request = $this->createPaymentRequest($order);

    $response = $this->getPaymentPlugin($order)
      ->getClient()
      ->createPayment($request);
    $this->eventDispatcher->dispatch(new ModelEvent(
      $response,
      $order,
      PaymentRequestBuilderInterface::PAYMENT_CREATE_RESPONSE_EVENT
    ));
    return $response;
  }

}
