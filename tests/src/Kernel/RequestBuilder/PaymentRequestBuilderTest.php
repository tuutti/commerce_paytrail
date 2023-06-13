<?php

declare(strict_types = 1);

namespace Drupal\Tests\commerce_paytrail\Kernel\RequestBuilder;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_paytrail\RequestBuilder\PaymentRequestBuilder;
use Drupal\commerce_paytrail\RequestBuilder\PaymentRequestBuilderInterface;
use GuzzleHttp\Psr7\Response;
use Paytrail\Payment\Model\Payment;
use Paytrail\Payment\Model\PaymentRequest;
use Paytrail\Payment\Model\PaymentRequestResponse;

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
      $this->container->get('http_client'),
      $this->container->get('commerce_price.minor_units_converter'),
      123,
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getRequest(OrderInterface $order) : PaymentRequest {
    return $this->getSut()->createPaymentRequest($order);
  }

  /**
   * @covers ::createHeaders
   * @covers ::get
   * @covers ::getResponse
   */
  public function testGet() : void {
    $this->setupMockHttpClient([
      new Response(200, [
        // The signature is just copied from ::validateSignature().
        'signature' => 'a22b396d0e5bef499654f73400632d530cf7c43efe64cd112c04660bd27036b8e3979906959256a28d7be8d5d302aa06c54d1597ad99f5cb56303f18acf01ab3',
      ],
        json_encode([
          'status' => 'ok',
          'amount' => '123',
          'currency' => 'EUR',
          'stamp' => '123',
          'reference' => '1',
          'created_at' => '123',
          'transaction_id' => '123',
        ])),
    ]);

    $order = $this->createOrder();
    $response = $this->getSut()->get('123', $order);

    static::assertCount(1, $this->requestHistory);
    $this->assertRequestHeaders($this->requestHistory[0]['request']);

    static::assertInstanceOf(Payment::class, $response);
    // Make sure event dispatcher was triggered for response.
    static::assertEquals(PaymentRequestBuilderInterface::PAYMENT_GET_RESPONSE_EVENT, $this->caughtEvents[0]->event);
    static::assertCount(1, $this->caughtEvents);
  }

  /**
   * @covers ::create
   * @covers ::createHeaders
   * @covers ::createPaymentRequest
   * @covers ::createOrderLine
   * @covers ::getResponse
   * @covers ::populatePaymentRequest
   */
  public function testCreate() : void {
    $this->setupMockHttpClient([
      new Response(201, [
        // The signature is just copied from ::validateSignature().
        'signature' => '84a0d7a81958c74064a31046365ce344fc38d96e168541684d6ea6596463169b0bd34819e0bab4f56a8a2378a0cb728a55d5a5902c59584ae039482eb449c0a2',
      ],
        json_encode([])),
    ]);

    $order = $this->createOrder();
    $response = $this->getSut()->create($order);
    static::assertCount(1, $this->requestHistory);
    $this->assertRequestHeaders($this->requestHistory[0]['request']);

    static::assertInstanceOf(PaymentRequestResponse::class, $response);
    // Make sure event dispatcher was triggered for both, request and response.
    static::assertEquals(PaymentRequestBuilderInterface::PAYMENT_CREATE_EVENT, $this->caughtEvents[0]->event);
    static::assertEquals(PaymentRequestBuilderInterface::PAYMENT_CREATE_RESPONSE_EVENT, $this->caughtEvents[1]->event);
    static::assertCount(2, $this->caughtEvents);
  }

}
