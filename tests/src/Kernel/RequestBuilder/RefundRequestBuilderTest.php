<?php

declare(strict_types = 1);

namespace Drupal\Tests\commerce_paytrail\Kernel\RequestBuilder;

use Drupal\commerce_paytrail\RequestBuilder\RefundRequestBuilder;
use Drupal\commerce_paytrail\RequestBuilder\RefundRequestBuilderInterface;
use Drupal\commerce_price\Price;
use Drupal\Tests\commerce_paytrail\Kernel\RequestBuilderKernelTestBase;
use GuzzleHttp\Psr7\Response;
use Paytrail\SDK\Response\RefundResponse;
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
      $this->container->get('commerce_price.minor_units_converter')
    );
  }

  /**
   * @covers ::__construct
   * @covers ::getPaymentPlugin
   * @covers ::createRefundRequest
   * @covers ::createHeaders
   * @covers ::getResponse
   */
  public function testCreateRefundRequest() : void {
    $order = $this->createOrder($this->createGatewayPlugin());
    $request = $this->getSut()->createRefundRequest($order, new Price('10', 'EUR'));

    // Make sure callback-type query parameter is set.
    static::assertStringEndsWith('event=refund-success', $request->getCallbackUrls()->getSuccess());
    static::assertStringEndsWith('event=refund-cancel', $request->getCallbackUrls()->getCancel());
    static::assertEquals(1, $request->getRefundReference());
    static::assertEquals(1000, $request->getAmount());
    static::assertIsString($request->getRefundStamp());
  }

  /**
   * @covers ::__construct
   * @covers ::createRefundRequest
   * @covers ::refund
   * @covers ::createHeaders
   * @covers ::getResponse
   */
  public function testRefund() : void {
    $this->setupMockHttpClient([
      new Response(201, [
        'signature' => '4393b6d81367fd8c71718d0895c3c51020984f25f2790d133f1c4a402dcfa51d',
      ],
        json_encode([
          'status' => 'ok',
          'transaction_id' => '123',
        ])),
    ]);

    $order = $this->createOrder($this->createGatewayPlugin());
    $response = $this->getSut()->refund('123', $order, new Price('10', 'EUR'));
    static::assertCount(1, $this->requestHistory);
    $this->assertRequestHeaders($this->requestHistory[0]['request']);

    static::assertInstanceOf(RefundResponse::class, $response);
    // Make sure event dispatcher was triggered for both, request and response.
    static::assertEquals(RefundRequestBuilderInterface::REFUND_CREATE, $this->caughtEvents[0]->event);
    static::assertEquals(RefundRequestBuilderInterface::REFUND_CREATE_RESPONSE, $this->caughtEvents[1]->event);
  }

}
