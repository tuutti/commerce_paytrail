<?php

declare(strict_types = 1);

namespace Drupal\commerce_paytrail\RequestBuilder;

use Drupal\commerce_paytrail\Event\ModelEvent;
use Drupal\commerce_paytrail\Header;
use Drupal\commerce_paytrail\PaymentGatewayPluginTrait;
use Drupal\commerce_paytrail\Plugin\Commerce\PaymentGateway\PaytrailInterface;
use Drupal\commerce_paytrail\SignatureTrait;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Uuid\UuidInterface;
use Paytrail\Payment\Configuration;
use Paytrail\Payment\Model\ModelInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * A base class for request builders.
 */
abstract class RequestBuilderBase implements RequestBuilderInterface {

  use PaymentGatewayPluginTrait;
  use SignatureTrait;

  /**
   * Constructs a new instance.
   *
   * @param \Drupal\Component\Uuid\UuidInterface $uuidService
   *   The uuid service.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $eventDispatcher
   *   The event dispatcher.
   */
  public function __construct(
    protected UuidInterface $uuidService,
    protected TimeInterface $time,
    protected EventDispatcherInterface $eventDispatcher,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public function createHeaders(
    string $method,
    Configuration $configuration,
    ?string $platformName = NULL,
    ?string $transactionId = NULL,
    ?string $tokenizationId = NULL,
  ) : Header {
    return new Header(
      $configuration->getApiKey('account'),
      'sha512',
      $method,
      $this->uuidService->generate(),
      $this->time->getCurrentTime(),
      $platformName ?: 'drupal/commerce_paytrail',
      $transactionId,
      $tokenizationId,
    );
  }

  /**
   * Gets the response.
   *
   * @param \Drupal\commerce_paytrail\Plugin\Commerce\PaymentGateway\PaytrailInterface $plugin
   *   The payment gateway plugin.
   * @param array $data
   *   The response data.
   * @param \Drupal\commerce_paytrail\Event\ModelEvent $event
   *   The event to respond to.
   *
   * @return \Paytrail\Payment\Model\ModelInterface
   *   The response.
   *
   * @throws \Drupal\commerce_paytrail\Exception\SecurityHashMismatchException
   */
  protected function getResponse(PaytrailInterface $plugin, array $data, ModelEvent $event) : ModelInterface {
    [$response, $body,, $headers] = $data;
    $this->validateSignature($plugin, $headers, $body);

    $this->eventDispatcher->dispatch($event);

    return $response;
  }

}
