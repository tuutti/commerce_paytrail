<?php

declare(strict_types = 1);

namespace Drupal\commerce_paytrail\RequestBuilder;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_paytrail\Exception\PaytrailPluginException;
use Drupal\commerce_paytrail\Exception\SecurityHashMismatchException;
use Drupal\commerce_paytrail\Header;
use Drupal\commerce_paytrail\Plugin\Commerce\PaymentGateway\Paytrail;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use Paytrail\Payment\Configuration;
use Paytrail\Payment\Model\ModelInterface;

/**
 * A base class for request builders.
 */
abstract class RequestBuilderBase {

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
   * Gets the default headers.
   *
   * @param string $method
   *   The HTTP method.
   * @param \Paytrail\Payment\Configuration $configuration
   *   The configuration.
   * @param string|null $transactionId
   *   The (optional) transaction id.
   * @param string|null $platformName
   *   The (optional) platform name.
   *
   * @return \Drupal\commerce_paytrail\Header
   *   The header.
   */
  public function getHeaders(
    string $method,
    Configuration $configuration,
    ?string $transactionId = NULL,
    ?string $platformName = NULL,
  ) : Header {
    return new Header(
      $configuration,
      'sha512',
      $method,
      $this->uuidService->generate(),
      (new DrupalDateTime('@' . $this->time->getCurrentTime()))->format('c'),
      $transactionId,
      $platformName
    );
  }

  /**
   * Gets the plugin for given order.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order.
   *
   * @return \Drupal\commerce_paytrail\Plugin\Commerce\PaymentGateway\Paytrail
   *   The payment plugin.
   */
  protected function getPlugin(OrderInterface $order) : Paytrail {
    static $plugins;

    if (!isset($plugins[$order->id()])) {
      $gateway = $order->get('payment_gateway');

      if ($gateway->isEmpty()) {
        throw new \InvalidArgumentException('Payment gateway not found.');
      }
      $plugin = $gateway->first()->entity->getPlugin();

      if (!$plugin instanceof Paytrail) {
        throw new PaytrailPluginException('Payment gateway not instanceof Klarna.');
      }
      $plugins[$order->id()] = $plugin;
    }

    return $plugins[$order->id()];
  }

  /**
   * Calculates a HMAC.
   *
   * @param string $secret
   *   The secret.
   * @param array $headers
   *   The headers.
   * @param string|null $body
   *   The body.
   *
   * @return string
   *   The signature.
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
   * Validates the response.
   *
   * @param \Drupal\commerce_paytrail\Plugin\Commerce\PaymentGateway\Paytrail $plugin
   *   The payment gateway plugin.
   * @param array $headers
   *   The headers.
   * @param string $body
   *   The body.
   *
   * @return $this
   *   The self.
   *
   * @throws \Drupal\commerce_paytrail\Exception\SecurityHashMismatchException
   */
  public function validateSignature(Paytrail $plugin, array $headers, string $body = '') : self {
    $signature = $this->signature($plugin->getClientConfiguration()->getApiKey('secret'), $headers, $body);

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
    $plugin = $this->getPlugin($order);
    $this->validateSignature($plugin, $headers, $body);

    return $response;
  }

}
