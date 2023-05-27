<?php

declare(strict_types = 1);

namespace Drupal\commerce_paytrail;

use Drupal\commerce_paytrail\Exception\SecurityHashMismatchException;
use Drupal\commerce_paytrail\Plugin\Commerce\PaymentGateway\PaytrailBase;

trait SignatureTrait {

  /**
   * {@inheritdoc}
   */
  protected function signature(string $secret, array $headers, ?string $body = '') : string {
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
  protected function validateSignature(PaytrailBase $plugin, array $headers, string $body = '') : self {
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

}
