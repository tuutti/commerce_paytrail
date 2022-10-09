<?php

declare(strict_types = 1);

namespace Drupal\Tests\commerce_paytrail\Kernel;

use Drupal\commerce_paytrail\RequestBuilder\RefundRequestBuilder;
use Drupal\commerce_price\Price;

/**
 * Tests Refund requests.
 *
 * @group commerce_paytrail
 * @coversDefaultClass \Drupal\commerce_paytrail\RequestBuilder\RefundRequestBuilder
 */
class RefundRequestBuilderTest extends RequestBuilderKernelTestBase {

  /**
   * The payment request builder.
   *
   * @var \Drupal\commerce_paytrail\RequestBuilder\RefundRequestBuilder
   */
  protected ?RefundRequestBuilder $sut;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->sut = $this->container->get('commerce_paytrail.refund_request');
  }

  /**
   * Tests createRefundRequest().
   */
  public function testCreateRefundRequest() : void {
    $order = $this->createOrder();
    $request = $this->sut->createRefundRequest($order, new Price('10', 'EUR'), '123');

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
   */
  public function testEventSubscriberEvent() : void {
    $this->assertCaughtEvents(1, function () {
      $order = $this->createOrder();
      $this->sut->createRefundRequest($order, $order->getTotalPrice(), '123');
    });
  }

}
