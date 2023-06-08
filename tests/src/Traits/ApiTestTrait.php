<?php

declare(strict_types = 1);

namespace Drupal\Tests\commerce_paytrail\Traits;

use Drupal\commerce_payment\Entity\PaymentGateway;
use Drupal\commerce_payment\Entity\PaymentGatewayInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;

/**
 * Api test trait.
 */
trait ApiTestTrait {

  /**
   * Creates HTTP client stub.
   *
   * @param \Psr\Http\Message\ResponseInterface[] $responses
   *   The expected responses.
   *
   * @return \GuzzleHttp\Client
   *   The client.
   */
  protected function createMockHttpClient(array $responses) : Client {
    $mock = new MockHandler($responses);
    $handlerStack = HandlerStack::create($mock);

    return new Client(['handler' => $handlerStack]);
  }

  /**
   * Overrides the default http_client service with mocked client.
   *
   * @param \Psr\Http\Message\ResponseInterface[] $responses
   *   The expected responses.
   */
  protected function setupMockHttpClient(array $responses) : void {
    $client = $this->createMockHttpClient($responses);
    $this->container->set('http_client', $client);
  }

  /**
   * Creates a new gateway plugin.
   *
   * @return \Drupal\commerce_payment\Entity\PaymentGatewayInterface
   *   The gateway plugin.
   */
  protected function createGatewayPlugin(
    string $id = 'paytrail',
    string $plugin = 'paytrail',
    array $configuration = []
  ) : PaymentGatewayInterface {
    $gateway = PaymentGateway::create([
      'id' => $id,
      'label' => 'Paytrail',
      'plugin' => $plugin,
    ]);

    if ($configuration) {
      $gateway->getPlugin()->setConfiguration($configuration);
    }
    $gateway->save();
    return $gateway;
  }

}
