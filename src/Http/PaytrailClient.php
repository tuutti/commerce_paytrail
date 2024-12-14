<?php

declare(strict_types=1);

namespace Drupal\commerce_paytrail\Http;

use GuzzleHttp\ClientInterface;
use Paytrail\SDK\Client;

/**
 * Extends the default Paytrail client.
 *
 * This allows us to set up a mock handler for the Guzzle http client
 * in tests.
 */
final class PaytrailClient extends Client {

  /**
   * Constructs a new instance.
   *
   * @param \GuzzleHttp\ClientInterface $client
   *   The HTTP client.
   * @param int $merchantId
   *   The merchant id.
   * @param string $secretKey
   *   The secret key.
   * @param string $platformName
   *   The platform name.
   */
  public function __construct(
    ClientInterface $client,
    int $merchantId,
    string $secretKey,
    string $platformName,
  ) {
    $this->http_client = $client;
    $this->merchantId = $merchantId;
    $this->secretKey = $secretKey;
    $this->platformName = $platformName;
  }

}
