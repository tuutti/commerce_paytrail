<?php

declare(strict_types = 1);

namespace Drupal\commerce_paytrail\RequestBuilder;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_paytrail\Header;
use Drupal\commerce_paytrail\PaymentGatewayPluginTrait;
use Drupal\commerce_paytrail\Plugin\Commerce\PaymentGateway\PaytrailBase;
use Drupal\commerce_paytrail\SignatureTrait;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Uuid\UuidInterface;
use Paytrail\Payment\Configuration;
use Paytrail\Payment\Model\ModelInterface;

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
   */
  public function __construct(
    protected UuidInterface $uuidService,
    protected TimeInterface $time
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public function createHeaders(
    string $method,
    Configuration $configuration,
    ?string $platformName = NULL,
  ) : Header {
    return new Header(
      $configuration->getApiKey('account'),
      'sha512',
      $method,
      $this->uuidService->generate(),
      $this->time->getCurrentTime(),
      $platformName
    );
  }

  /**
   * Gets the response.
   *
   * @param \Drupal\commerce_paytrail\Plugin\Commerce\PaymentGateway\PaytrailBase $plugin
   *   The payment gateway plugin.
   * @param array $data
   *   The response data.
   *
   * @return \Paytrail\Payment\Model\ModelInterface
   *   The response.
   *
   * @throws \Drupal\commerce_paytrail\Exception\SecurityHashMismatchException
   */
  protected function getResponse(PaytrailBase $plugin, array $data) : ModelInterface {
    [$response, $body,, $headers] = $data;
    $this->validateSignature($plugin, $headers, $body);

    return $response;
  }

}
