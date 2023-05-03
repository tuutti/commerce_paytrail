<?php

declare(strict_types = 1);

namespace Drupal\commerce_paytrail;

/**
 * The header value object.
 */
final class Header {

  /**
   * Constructs a new instance.
   *
   * @param string $account
   *   The configuration.
   * @param string $hashAlgorithm
   *   The hash algorithm.
   * @param string $method
   *   The method.
   * @param string $nonce
   *   The nonce.
   * @param int|string $timestamp
   *   The timestamp.
   * @param string|null $transactionId
   *   The transactionId.
   * @param string|null $platformName
   *   The platform name.
   */
  public function __construct(
    public string $account,
    public string $hashAlgorithm,
    public string $method,
    public string $nonce,
    public int|string $timestamp,
    public ?string $transactionId = NULL,
    public ?string $platformName = NULL
  ) {
  }

  /**
   * Converts headers to array.
   *
   * @return array
   *   The headers.
   */
  public function toArray() : array {
    $array = [
      'checkout-account' => $this->account,
      'checkout-algorithm' => $this->hashAlgorithm,
      'checkout-method' => $this->method,
      'checkout-nonce' => $this->nonce,
      'checkout-timestamp' => (int) $this->timestamp,
      'platform-name' => $this->platformName ?: 'drupal/commerce_paytrail',
    ];
    if ($this->transactionId) {
      $array['checkout-transaction-id'] = $this->transactionId;
    }

    return $array;
  }

}
