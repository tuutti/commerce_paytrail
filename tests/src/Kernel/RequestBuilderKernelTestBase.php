<?php

declare(strict_types = 1);

namespace Drupal\Tests\commerce_paytrail\Kernel;

use Drupal\commerce_payment\Entity\PaymentInterface;
use Drupal\commerce_paytrail\Event\ModelEvent;
use Drupal\commerce_paytrail\Plugin\Commerce\PaymentGateway\Paytrail;
use Drupal\commerce_paytrail\RequestBuilder\PaymentRequestBuilderInterface;
use Drupal\commerce_paytrail\RequestBuilder\RefundRequestBuilderInterface;
use Drupal\commerce_paytrail\RequestBuilder\RequestBuilderInterface;
use Drupal\Tests\commerce_paytrail\Traits\EventSubscriberTestTrait;
use Drupal\Tests\commerce_paytrail\Traits\OrderTestTrait;
use Drupal\Tests\commerce_paytrail\Traits\TaxTestTrait;
use Prophecy\Argument;
use Prophecy\Prophecy\ObjectProphecy;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Base class for request builder tests.
 */
abstract class RequestBuilderKernelTestBase extends PaytrailKernelTestBase implements EventSubscriberInterface {

  use EventSubscriberTestTrait;
  use TaxTestTrait;
  use OrderTestTrait;

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
   * Creates a Paytrail plugin using mocked request builder.
   *
   * @param \Drupal\commerce_paytrail\RequestBuilder\RequestBuilderInterface $builder
   *   The mocked request builder.
   *
   * @return \Drupal\commerce_paytrail\Plugin\Commerce\PaymentGateway\Paytrail
   *   The payment gateway plugin.
   */
  protected function getGatewayPluginForBuilder(RequestBuilderInterface $builder) : Paytrail {
    $service = match(TRUE) {
      $builder instanceof PaymentRequestBuilderInterface => 'commerce_paytrail.payment_request',
      $builder instanceof RefundRequestBuilderInterface => 'commerce_paytrail.refund_request',
    };
    $this->container->set($service, $builder);
    $this->refreshServices();

    $gateway = $this->createGatewayPlugin('test');
    $this->gateway = $gateway;

    return $gateway->getPlugin();
  }

  /**
   * Create mock request.
   *
   * @param int|string $orderId
   *   The order id.
   *
   * @return \Symfony\Component\HttpFoundation\Request
   *   The request.
   */
  protected function createRequest(int|string $orderId) : Request {
    $request = Request::createFromGlobals();
    $request->query->set('checkout-reference', $orderId);
    $request->query->set('checkout-transaction-id', '123');
    $request->query->set('checkout-stamp', '123');

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
   * Creates a mock builder for PaymentRequest builder.
   *
   * @return \Prophecy\Prophecy\ObjectProphecy
   *   The mock builder.
   */
  protected function createRequestBuilderMock() : ObjectProphecy {
    $builder = $this->prophesize(PaymentRequestBuilderInterface::class);
    $builder->validateSignature(Argument::any(), Argument::any())
      ->shouldBeCalled()
      ->willReturn($builder->reveal());
    return $builder;
  }

  /**
   * {@inheritdoc}
   */
  public static function getEventClassName(): string {
    return ModelEvent::class;
  }

}
