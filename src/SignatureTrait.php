<?php

declare(strict_types = 1);

namespace Drupal\commerce_paytrail;

use Drupal\commerce_paytrail\Exception\SecurityHashMismatchException;

/**
 * A trait to create and validate Paytrail signatures.
 */
trait SignatureTrait {

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

    return hash_hmac('sha256', implode("\n", $payload), $secret);
  }

  /**
   * Validates the response.
   *
   * @param string $secret
   *   The secret.
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
  public function validateSignature(string $secret, array $headers, string $body = '') : self {
    if (empty($secret)) {
      throw new SecurityHashMismatchException('Secret is empty.');
    }
    $signature = $this->signature(
      $secret,
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

}
