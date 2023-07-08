<?php

declare(strict_types = 1);

namespace Drupal\Tests\commerce_paytrail\Kernel\RequestBuilder;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_paytrail\RequestBuilder\PaymentRequestBuilder;
use Drupal\commerce_paytrail\RequestBuilder\PaymentRequestBuilderInterface;
use GuzzleHttp\Psr7\Response;
use Paytrail\SDK\Request\AbstractPaymentRequest;
use Paytrail\SDK\Response\PaymentResponse;
use Paytrail\SDK\Response\PaymentStatusResponse;

/**
 * Tests Payment requests.
 *
 * @group commerce_paytrail
 * @coversDefaultClass \Drupal\commerce_paytrail\RequestBuilder\PaymentRequestBuilder
 */
class PaymentRequestBuilderTest extends PaymentRequestBuilderTestBase {

  /**
   * Gets the SUT.
   *
   * @return \Drupal\commerce_paytrail\RequestBuilder\PaymentRequestBuilder
   *   The system under testing.
   */
  private function getSut() : PaymentRequestBuilder {
    return new PaymentRequestBuilder(
      $this->container->get('uuid'),
      $this->container->get('datetime.time'),
      $this->container->get('event_dispatcher'),
      $this->container->get('commerce_price.minor_units_converter'),
      123,
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getRequest(OrderInterface $order) : AbstractPaymentRequest {
    return $this->getSut()->createPaymentRequest($order);
  }

  /**
   * @covers ::get
   * @covers \Drupal\commerce_paytrail\EventSubscriber\PaymentRequestSubscriberBase::isValid
   */
  public function testGet() : void {
    $this->setupMockHttpClient([
      new Response(200, [
        'signature' => '79e472405352ae8cb3df67b93d3ef5fe55a5c0fca11277f0569665d81d142698',
      ],
        json_encode([
          'status' => 'ok',
          'amount' => 123,
          'currency' => 'EUR',
          'stamp' => '123',
          'reference' => '1',
          'createdAt' => '123',
          'transactionId' => '123',
        ])),
    ]);
    $order = $this->createOrder($this->createGatewayPlugin());
    $response = $this->getSut()->get('123', $order);

    static::assertCount(1, $this->requestHistory);
    $this->assertRequestHeaders($this->requestHistory[0]['request']);

    static::assertInstanceOf(PaymentStatusResponse::class, $response);
    // Make sure event dispatcher was triggered for response.
    static::assertEquals(PaymentRequestBuilderInterface::PAYMENT_GET_RESPONSE_EVENT, $this->caughtEvents[0]->event);
    static::assertCount(1, $this->caughtEvents);
  }

  /**
   * @covers ::create
   * @covers ::createPaymentRequest
   * @covers ::createOrderLine
   * @covers ::populatePaymentRequest
   * @covers ::orderHasDiscounts
   * @covers \Drupal\commerce_paytrail\Plugin\Commerce\PaymentGateway\PaytrailBase::getClient
   * @covers \Drupal\commerce_paytrail\Http\PaytrailClientFactory::create
   * @covers \Drupal\commerce_paytrail\EventSubscriber\PaymentRequestSubscriberBase::isValid
   */
  public function testCreate() : void {
    $this->setupMockHttpClient([
      new Response(201, [
        'signature' => 'd8e92eed91585b7a1ad80761064db7d7e9b960a69cd15f6c2581c5d3142ceaa2',
      ],
        json_encode([])),
    ]);

    $order = $this->createOrder($this->createGatewayPlugin());
    $response = $this->getSut()->create($order);
    static::assertCount(1, $this->requestHistory);
    $this->assertRequestHeaders($this->requestHistory[0]['request']);

    static::assertInstanceOf(PaymentResponse::class, $response);
    // Make sure event dispatcher was triggered for both, request and response.
    static::assertEquals(PaymentRequestBuilderInterface::PAYMENT_CREATE_EVENT, $this->caughtEvents[0]->event);
    static::assertEquals(PaymentRequestBuilderInterface::PAYMENT_CREATE_RESPONSE_EVENT, $this->caughtEvents[1]->event);
    static::assertCount(2, $this->caughtEvents);
  }

}
