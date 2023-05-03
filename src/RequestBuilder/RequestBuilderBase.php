<?php

declare(strict_types = 1);

namespace Drupal\commerce_paytrail\RequestBuilder;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_paytrail\Exception\SecurityHashMismatchException;
use Drupal\commerce_paytrail\Header;
use Drupal\commerce_paytrail\PaymentGatewayPluginTrait;
use Drupal\commerce_paytrail\Plugin\Commerce\PaymentGateway\Paytrail;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Uuid\UuidInterface;
use Paytrail\Payment\Configuration;
use Paytrail\Payment\Model\ModelInterface;

/**
 * A base class for request builders.
 */
abstract class RequestBuilderBase implements RequestBuilderInterface {

  use PaymentGatewayPluginTrait;

  /**
   * Constructs a new instance.
   *
   * @param \Drupal\Component\Uuid\UuidInterface $uuidService
   *   The uuid service.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   */
  public function __construct(
    protected UuidInterface $uuidService,
    protected TimeInterface $time
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public function createHeaders(
    string $method,
    Configuration $configuration,
    ?string $transactionId = NULL,
    ?string $platformName = NULL,
  ) : Header {
    return new Header(
      $configuration->getApiKey('account'),
      'sha512',
      $method,
      $this->uuidService->generate(),
      $this->time->getCurrentTime(),
      $transactionId,
      $platformName
    );
  }

  /**
   * {@inheritdoc}
   */
  public function signature(string $secret, array $headers, ?string $body = '') : string {
    // Filter non-checkout headers.
    $headers = array_filter(
      $headers,
      fn (string $key) => str_starts_with($key, 'checkout-'),
      ARRAY_FILTER_USE_KEY
    );

    $includeKeys = array_keys($headers);
    sort($includeKeys, SORT_STRING);

    $payload = array_map(function ($key) use ($headers) {
      // Make sure we have a flat key=>value array.
      $value = is_array($headers[$key]) ? reset($headers[$key]) : $headers[$key];

      return implode(':', [$key, $value]);
    }, $includeKeys);

    $payload[] = $body;

    return hash_hmac('sha512', implode("\n", $payload), $secret);
  }

  /**
   * {@inheritdoc}
   */
  public function validateSignature(Paytrail $plugin, array $headers, string $body = '') : self {
    $signature = $this->signature(
      $plugin->getClientConfiguration()->getApiKey('secret'),
      $headers,
      $body
    );

    if (!isset($headers['signature'])) {
      throw new SecurityHashMismatchException('Signature missing.');
    }
    ['signature' => $expected] = $headers;

    // Guzzle response header is always an array.
    if (is_array($expected)) {
      $expected = reset($expected);
    }
    if ($expected !== $signature) {
      throw new SecurityHashMismatchException('Signature does not match.');
    }
    return $this;
  }

  /**
   * Gets the response.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order.
   * @param array $data
   *   The response data.
   *
   * @return \Paytrail\Payment\Model\ModelInterface
   *   The response.
   *
   * @throws \Drupal\commerce_paytrail\Exception\PaytrailPluginException
   * @throws \Drupal\commerce_paytrail\Exception\SecurityHashMismatchException
   */
  protected function getResponse(OrderInterface $order, array $data) : ModelInterface {
    [$response, $body,, $headers] = $data;
    $plugin = $this->getPaymentPlugin($order);
    $this->validateSignature($plugin, $headers, $body);

    return $response;
  }

}
