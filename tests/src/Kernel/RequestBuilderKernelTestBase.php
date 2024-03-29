<?php

declare(strict_types = 1);

namespace Drupal\Tests\commerce_paytrail\Kernel;

use Drupal\commerce_payment\Entity\PaymentGatewayInterface;
use Drupal\commerce_payment\Entity\PaymentInterface;
use Drupal\commerce_paytrail\Event\ModelEvent;
use Drupal\commerce_paytrail\Plugin\Commerce\PaymentGateway\PaytrailBase;
use Drupal\commerce_paytrail\RequestBuilder\PaymentRequestBuilderInterface;
use Drupal\commerce_paytrail\RequestBuilder\RefundRequestBuilderInterface;
use Drupal\commerce_paytrail\RequestBuilder\TokenRequestBuilderInterface;
use Drupal\Tests\commerce_paytrail\Traits\EventSubscriberTestTrait;
use Drupal\Tests\commerce_paytrail\Traits\OrderTestTrait;
use Drupal\Tests\commerce_paytrail\Traits\TaxTestTrait;
use GuzzleHttp\Psr7\Request as PsrRequest;
use Paytrail\SDK\Util\Signature;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Base class for request builder tests.
 */
abstract class RequestBuilderKernelTestBase extends PaytrailKernelTestBase implements EventSubscriberInterface {

  use EventSubscriberTestTrait;
  use TaxTestTrait;
  use OrderTestTrait;
  use ProphecyTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'commerce_tax',
    'commerce_checkout',
    'commerce_promotion',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('commerce_payment');
    $this->installEntitySchema('commerce_promotion');
    $this->installConfig([
      'commerce_checkout',
      'commerce_promotion',
    ]);
    $this->setupTaxes()
      ->setPricesIncludeTax(FALSE);
  }

  /**
   * Creates a Paytrail plugin using mocked request builders.
   *
   * @param \Drupal\commerce_paytrail\RequestBuilder\PaymentRequestBuilderInterface|null $paymentRequestBuilder
   *   The request payment builder or null.
   * @param \Drupal\commerce_paytrail\RequestBuilder\RefundRequestBuilderInterface|null $refundRequestBuilder
   *   The refund request builder or null.
   * @param \Drupal\commerce_paytrail\RequestBuilder\TokenRequestBuilderInterface|null $tokenPaymentRequestBuilder
   *   The token payment request builder or null.
   * @param string $plugin
   *   The payment plugin id.
   *
   * @return \Drupal\commerce_payment\Entity\PaymentGatewayInterface
   *   The payment gateway.
   */
  protected function mockPaymentGateway(
    PaymentRequestBuilderInterface $paymentRequestBuilder = NULL,
    RefundRequestBuilderInterface $refundRequestBuilder = NULL,
    TokenRequestBuilderInterface $tokenPaymentRequestBuilder = NULL,
    string $plugin = 'paytrail',
  ) : PaymentGatewayInterface {
    if ($paymentRequestBuilder) {
      $this->container->set('commerce_paytrail.payment_request', $paymentRequestBuilder);
    }
    if ($refundRequestBuilder) {
      $this->container->set('commerce_paytrail.refund_request', $refundRequestBuilder);
    }
    if ($tokenPaymentRequestBuilder) {
      $this->container->set('commerce_paytrail.token_payment_request', $tokenPaymentRequestBuilder);
    }
    $this->refreshServices();

    return $this->createGatewayPlugin($plugin, $plugin);
  }

  /**
   * Create mock request.
   *
   * @param \Drupal\commerce_paytrail\Plugin\Commerce\PaymentGateway\PaytrailBase $plugin
   *   The payment plugin.
   * @param array $query
   *   The query parameters.
   *
   * @return \Symfony\Component\HttpFoundation\Request
   *   The request.
   */
  protected function createRequest(PaytrailBase $plugin, array $query = []) : Request {
    $request = Request::createFromGlobals();

    foreach ($query as $key => $value) {
      $request->query->set($key, $value);
    }
    $request->query
      ->set(
        'signature',
        Signature::calculateHmac(
          $request->query->all(),
          secretKey: $plugin->getSecret(),
        )
      );

    return $request;
  }

  /**
   * Loads payment for given transaction id.
   *
   * @param string $transactionId
   *   The transaction id used to load the payment.
   *
   * @return \Drupal\commerce_payment\Entity\PaymentInterface|null
   *   The payment or null.
   */
  protected function loadPayment(string $transactionId) :?  PaymentInterface {
    return $this->container
      ->get('entity_type.manager')
      ->getStorage('commerce_payment')
      ->loadByRemoteId($transactionId);
  }

  /**
   * {@inheritdoc}
   */
  public static function getEventClassName(): string {
    return ModelEvent::class;
  }

  /**
   * Asserts that request has required headers.
   *
   * @param \GuzzleHttp\Psr7\Request $request
   *   The request.
   */
  protected function assertRequestHeaders(PsrRequest $request) : void {
    static::assertEquals('drupal/commerce_paytrail', $request->getHeader('platform-name')[0]);
  }

}
