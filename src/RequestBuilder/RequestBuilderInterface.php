<?php

declare(strict_types = 1);

namespace Drupal\commerce_paytrail\RequestBuilder;

use Drupal\commerce_paytrail\Header;
use Drupal\commerce_paytrail\Plugin\Commerce\PaymentGateway\Paytrail;
use Paytrail\Payment\Configuration;

/**
 * Request builder interface.
 */
interface RequestBuilderInterface {

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
  public function createHeaders(
    string $method,
    Configuration $configuration,
    ?string $transactionId = NULL,
    ?string $platformName = NULL,
  ) : Header;

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
  public function signature(string $secret, array $headers, ?string $body = '') : string;

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
  public function validateSignature(Paytrail $plugin, array $headers, string $body = '') : self;

}
