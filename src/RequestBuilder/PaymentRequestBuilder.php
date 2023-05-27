<?php

declare(strict_types = 1);

namespace Drupal\commerce_paytrail\RequestBuilder;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_paytrail\Plugin\Commerce\PaymentGateway\Paytrail;
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
final class PaymentRequestBuilder extends PaymentRequestBase {

  /**
   * {@inheritdoc}
   */
  public function get(string $transactionId, Paytrail $plugin) : Payment {
    $configuration = $plugin->getClientConfiguration();
    $headers = $this->createHeaders('GET', $configuration);
    $headers->transactionId = $transactionId;

    $response = (new PaymentsApi($this->client, $configuration))
      ->getPaymentByTransactionIdWithHttpInfo(
        $transactionId,
        $configuration->getApiKey('account'),
        $headers->hashAlgorithm,
        $headers->method,
        $transactionId,
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
  public function create(Paytrail $plugin, OrderInterface $order) : PaymentRequestResponse {
    $configuration = $plugin
      ->getClientConfiguration();
    $headers = $this->createHeaders('POST', $configuration);
    $request = $this->populateRequest(new PaymentRequest(), $order);

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
