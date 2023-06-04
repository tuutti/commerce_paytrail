<?php

declare(strict_types = 1);

namespace Drupal\Tests\commerce_paytrail\Kernel\RequestBuilder;

use Drupal\commerce_paytrail\RequestBuilder\RefundRequestBuilder;
use Drupal\commerce_paytrail\RequestBuilder\RefundRequestBuilderInterface;
use Drupal\commerce_price\Price;
use Drupal\Tests\commerce_paytrail\Kernel\RequestBuilderKernelTestBase;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Paytrail\Payment\Model\RefundResponse;
use Prophecy\PhpUnit\ProphecyTrait;

/**
 * Tests Refund requests.
 *
 * @group commerce_paytrail
 * @coversDefaultClass \Drupal\commerce_paytrail\RequestBuilder\RefundRequestBuilder
 */
class RefundRequestBuilderTest extends RequestBuilderKernelTestBase {

  use ProphecyTrait;

  /**
   * Gets the system under testing.
   *
   * @return \Drupal\commerce_paytrail\RequestBuilder\RefundRequestBuilder
   *   The SUT.
   */
  private function getSut() : RefundRequestBuilder {
    return new RefundRequestBuilder(
      $this->container->get('uuid'),
      $this->container->get('datetime.time'),
      $this->container->get('event_dispatcher'),
      $this->container->get('http_client'),
      $this->container->get('commerce_price.minor_units_converter')
    );
  }

  /**
   * @covers ::__construct
   * @covers ::getPaymentPlugin
   * @covers ::createRefundRequest
   */
  public function testCreateRefundRequest() : void {
    $order = $this->createOrder();
    $request = $this->getSut()->createRefundRequest($order, new Price('10', 'EUR'), '123');

    foreach (['success', 'cancel'] as $type) {
      // Make sure callback-type query parameter is set.
      static::assertStringEndsWith('event=refund-' . $type, $request->getCallbackUrls()[$type]);
    }
    static::assertEquals(1, $request->getRefundReference());
    static::assertEquals(1000, $request->getAmount());
    static::assertEquals('123', $request->getRefundStamp());
  }

  /**
   * Make sure we can subscribe to model events.
   *
   * @covers ::createRefundRequest
   * @covers ::getPaymentPlugin
   */
  public function testEventSubscriberEvent() : void {
    $this->assertCaughtEvents(1, function () {
      $order = $this->createOrder();
      $this->getSut()->createRefundRequest($order, $order->getTotalPrice(), '123');
    });
  }

  /**
   * @covers ::createRefundRequest
   * @covers ::refund
   */
  public function testRefund() : void {
    $mock = new MockHandler([
      new Response(201, [
        // The signature is just copied from ::validateSignature().
        'signature' => '6eb50789b1bbe9e713e9268ae1ff7197c5cadfeb9a32a69982182f1584d6fbd71812192ca217ae7aaafe268d1ff6c99ef206f70cf171eb3c2114743e430fe0da',
      ],
        json_encode([
          'status' => 'ok',
          'transaction_id' => '123',
        ])),
    ]);

    $handlerStack = HandlerStack::create($mock);
    $client = new Client(['handler' => $handlerStack]);
    $this->container->set('http_client', $client);

    $order = $this->createOrder();
    $response = $this->getSut()->refund('123', $order, new Price('10', 'EUR'));
    $this->assertInstanceOf(RefundResponse::class, $response);
    // Make sure event dispatcher was triggered for both, request and response.
    $this->assertEquals(RefundRequestBuilderInterface::REFUND_CREATE, $this->caughtEvents[0]->event);
    $this->assertEquals(RefundRequestBuilderInterface::REFUND_CREATE_RESPONSE, $this->caughtEvents[1]->event);
  }

}
