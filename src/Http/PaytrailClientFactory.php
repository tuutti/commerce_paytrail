<?php

declare(strict_types = 1);

namespace Drupal\commerce_paytrail\Http;

use Drupal\Core\Http\ClientFactory;
use Paytrail\SDK\Client;

/**
 * A factory to initialize Paytrail clients.
 */
final class PaytrailClientFactory {

  /**
   * Constructs a new instance.
   *
   * @param \Drupal\Core\Http\ClientFactory $clientFactory
   *   The HTTP client factory.
   */
  public function __construct(private ClientFactory $clientFactory) {
  }

  /**
   * Constructs a new paytrail client.
   *
   * @param int $merchantId
   *   The merchant id.
   * @param string $merchantSecret
   *   The merchant secret.
   * @param string $platformName
   *   The platform name.
   *
   * @return \Drupal\commerce_paytrail\Http\PaytrailClient
   *   The initialized paytrail client.
   */
  public function create(int $merchantId, string $merchantSecret, string $platformName) : PaytrailClient {
    $client = $this->clientFactory->fromOptions([
      'base_uri' => Client::API_ENDPOINT,
      'timeout' => Client::DEFAULT_TIMEOUT,
    ]);
    return new PaytrailClient($client, $merchantId, $merchantSecret, $platformName);
  }

}
