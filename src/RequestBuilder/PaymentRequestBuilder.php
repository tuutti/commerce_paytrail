<?php

declare(strict_types = 1);

namespace Drupal\commerce_paytrail\RequestBuilder;

use Drupal\commerce_order\Entity\OrderInterface;
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
    $headers = $this->createHeaders('GET', $configuration);
    $headers->transactionId = $transactionId;

    $response = (new PaymentsApi($this->client, $configuration))
      ->getPaymentByTransactionIdWithHttpInfo(
        $headers->transactionId,
        $configuration->getApiKey('account'),
        $headers->hashAlgorithm,
        $headers->method,
        $headers->transactionId,
        $headers->timestamp,
        $headers->nonce,
        $this->signature(
          $configuration->getApiKey('secret'),
          $headers->toArray(),
        ),
      );
    return $this->getResponse($plugin, $response);
  }

  /**
   * {@inheritdoc}
   */
  public function createPaymentRequest(OrderInterface $order) : PaymentRequest {
    return $this->populatePaymentRequest(new PaymentRequest(), $order);
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
        $configuration->getApiKey('account'),
        $headers->hashAlgorithm,
        $headers->method,
        $headers->timestamp,
        $headers->nonce,
        $this->signature(
          $configuration->getApiKey('secret'),
          $headers->toArray(),
          json_encode(ObjectSerializer::sanitizeForSerialization($request), JSON_THROW_ON_ERROR)
        ),
      );
    return $this->getResponse($plugin, $response);
  }

}
