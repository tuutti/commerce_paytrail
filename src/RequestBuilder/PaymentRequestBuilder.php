<?php

declare(strict_types = 1);

namespace Drupal\commerce_paytrail\RequestBuilder;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_paytrail\Event\ModelEvent;
use Paytrail\Payment\Api\PaymentsApi;
use Paytrail\Payment\Model\Payment;
use Paytrail\Payment\Model\PaymentRequest;
use Paytrail\Payment\Model\PaymentRequestResponse;
use Paytrail\Payment\ObjectSerializer;

/**
 * The payment request builder.
 *
 * @internal
 */
final class PaymentRequestBuilder extends PaymentRequestBase implements PaymentRequestBuilderInterface {

  /**
   * {@inheritdoc}
   */
  public function get(string $transactionId, OrderInterface $order) : Payment {
    $plugin = $this->getPaymentPlugin($order);

    $configuration = $plugin->getClientConfiguration();
    $headers = $this->createHeaders('GET', $configuration, transactionId: $transactionId);

    $response = (new PaymentsApi($this->client, $configuration))
      ->getPaymentByTransactionIdWithHttpInfo(
        transaction_id: $headers->transactionId,
        checkout_account: $configuration->getApiKey('account'),
        checkout_algorithm: $headers->hashAlgorithm,
        checkout_method: $headers->method,
        checkout_transaction_id: $headers->transactionId,
        checkout_timestamp: $headers->timestamp,
        checkout_nonce: $headers->nonce,
        platform_name: $headers->platformName,
        signature: $this->signature(
          $configuration->getApiKey('secret'),
          $headers->toArray(),
        ),
      );
    return $this->getResponse($plugin, $response,
      new ModelEvent(
        $response[0],
        $headers,
        $order,
        PaymentRequestBuilderInterface::PAYMENT_GET_RESPONSE_EVENT
      )
    );
  }

  /**
   * {@inheritdoc}
   */
  public function createPaymentRequest(OrderInterface $order) : PaymentRequest {
    return $this->populatePaymentRequest(
      new PaymentRequest(),
      $order,
      PaymentRequestBuilderInterface::PAYMENT_CREATE_EVENT
    );
  }

  /**
   * {@inheritdoc}
   */
  public function create(OrderInterface $order) : PaymentRequestResponse {
    $plugin = $this->getPaymentPlugin($order);

    $configuration = $plugin
      ->getClientConfiguration();

    $headers = $this->createHeaders('POST', $configuration);
    $request = $this->createPaymentRequest($order);

    $response = (new PaymentsApi($this->client, $configuration))
      ->createPaymentWithHttpInfo(
        $request,
        checkout_account: $configuration->getApiKey('account'),
        checkout_algorithm: $headers->hashAlgorithm,
        checkout_method: $headers->method,
        checkout_timestamp: $headers->timestamp,
        checkout_nonce: $headers->nonce,
        platform_name: $headers->platformName,
        signature: $this->signature(
          $configuration->getApiKey('secret'),
          $headers->toArray(),
          json_encode(ObjectSerializer::sanitizeForSerialization($request), JSON_THROW_ON_ERROR)
        ),
      );
    return $this->getResponse($plugin, $response,
      new ModelEvent(
        $response[0],
        $headers,
        $order,
        PaymentRequestBuilderInterface::PAYMENT_CREATE_RESPONSE_EVENT
      )
    );
  }

}
