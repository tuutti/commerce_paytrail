<?php

declare(strict_types = 1);

namespace Drupal\Tests\commerce_paytrail\Traits;

use Drupal\commerce_payment\Entity\PaymentGateway;
use Drupal\commerce_payment\Entity\PaymentGatewayInterface;
use Drupal\Core\Http\ClientFactory;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;

/**
 * Api test trait.
 */
trait ApiTestTrait {

  /**
   * The request history.
   *
   * @var array
   */
  protected array $requestHistory = [];

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
    $history = Middleware::history($this->requestHistory);
    $mock = new MockHandler($responses);
    $handlerStack = HandlerStack::create($mock);
    $handlerStack->push($history);

    return new Client(['handler' => $handlerStack]);
  }

  /**
   * Overrides the default http_client service with mocked client.
   *
   * @param \Psr\Http\Message\ResponseInterface[] $responses
   *   The expected responses.
   *
   * @return \GuzzleHttp\Client
   *   The client.
   */
  protected function setupMockHttpClient(array $responses) : Client {
    $client = $this->createMockHttpClient($responses);

    $this->container->set('http_client_factory', new class ($client) extends ClientFactory {

      /**
       * Constructs a new instance.
       *
       * @param \GuzzleHttp\ClientInterface $client
       *   The http client.
       */
      public function __construct(private ClientInterface $client) {
      }

      /**
       * {@inheritdoc}
       */
      public function fromOptions(array $config = []) {
        return $this->client;
      }

    });
    return $client;
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
