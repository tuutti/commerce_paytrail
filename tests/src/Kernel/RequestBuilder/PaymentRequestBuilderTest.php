<?php

declare(strict_types = 1);

namespace Drupal\Tests\commerce_paytrail\Kernel\RequestBuilder;

use Drupal\commerce_order\Adjustment;
use Drupal\commerce_paytrail\Plugin\Commerce\PaymentGateway\PaytrailInterface;
use Drupal\commerce_paytrail\RequestBuilder\PaymentRequestBuilder;
use Drupal\commerce_paytrail\RequestBuilder\PaymentRequestBuilderInterface;
use Drupal\commerce_price\Price;
use Drupal\profile\Entity\Profile;
use Drupal\Tests\commerce_paytrail\Kernel\RequestBuilderKernelTestBase;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Paytrail\Payment\Model\Address;
use Paytrail\Payment\Model\Payment;
use Paytrail\Payment\Model\PaymentRequest;
use Paytrail\Payment\Model\PaymentRequestResponse;

/**
 * Tests Payment requests.
 *
 * @group commerce_paytrail
 * @coversDefaultClass \Drupal\commerce_paytrail\RequestBuilder\PaymentRequestBuilder
 */
class PaymentRequestBuilderTest extends RequestBuilderKernelTestBase {

  /**
   * Gets the system under testing.
   *
   * @return \Drupal\commerce_paytrail\RequestBuilder\PaymentRequestBuilder
   *   The SUT.
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
   * Asserts order taxes.
   *
   * @param \Paytrail\Payment\Model\PaymentRequest $request
   *   The request to validate.
   * @param int $expectedTotalPrice
   *   The expected total price.
   * @param int $expectedUnitPrice
   *   The expected unit price.
   * @param int $expectedVatPercentage
   *   The expected vat percentage.
   */
  private function assertTaxes(
    PaymentRequest $request,
    int $expectedTotalPrice,
    int $expectedUnitPrice,
    int $expectedVatPercentage
  ) : void {
    $orderItem = $request->getItems()[0];
    static::assertEquals($expectedTotalPrice, $request->getAmount());
    static::assertEquals($expectedUnitPrice, $orderItem->getUnitPrice());
    static::assertEquals($expectedVatPercentage, $orderItem->getVatPercentage());
  }

  /**
   * Tests ::createPaymentRequest().
   */
  public function testPaymentRequest() : void {
    $order = $this->createOrder();

    $request = $this->getSut()->createPaymentRequest($order);
    static::assertInstanceOf(PaymentRequest::class, $request);
    static::assertCount(1, $request->getItems());
    static::assertEquals($order->id(), $request->getReference());
    static::assertNotEmpty($request->getStamp());
    static::assertEquals('EN', $request->getLanguage());
    static::assertEquals('EUR', $request->getCurrency());

    $orderItem = $request->getItems()[0];
    static::assertEquals(2, $orderItem->getUnits());
    // Order has no taxes by default.
    static::assertTaxes($request, 2200, 1100, 0);
  }

  /**
   * Make sure taxes are included in prices.
   */
  public function testPricesIncludeTax() : void {
    $order = $this
      ->setPricesIncludeTax(TRUE, ['FI'])
      ->createOrder();

    $request = $this->getSut()->createPaymentRequest($order);
    // Order should have prices included in unit prices.
    $this->assertTaxes($request, 2200, 1100, 24);
  }

  /**
   * Make sure taxes are added to total price.
   */
  public function testPricesIncludeNoTax() : void {
    $order = $this
      ->setPricesIncludeTax(FALSE, ['FI'])
      ->createOrder();

    $request = $this->getSut()->createPaymentRequest($order);
    // Taxes should be added to unit price.
    $this->assertTaxes($request, 2728, 1364, 24);
  }

  /**
   * Make sure discounts are included.
   */
  public function testDiscount() : void {
    $order = $this
      ->setPricesIncludeTax(TRUE, ['FI'])
      ->createOrder([
        new Adjustment([
          'type' => 'custom',
          'label' => 'Discount',
          'amount' => new Price('-5', 'EUR'),
        ]),
      ]);
    $request = $this->getSut()->createPaymentRequest($order);
    // Make sure order items are not removed.
    static::assertNotNull($request->getItems());

    $this->assertTaxes($request, 1700, 850, 24);
  }

  /**
   * Make sure order level discounts remove items if configured so.
   */
  public function testOrderLevelDiscount() : void {
    $this->gateway->getPlugin()->setConfiguration([
      'order_discount_strategy' => PaytrailInterface::STRATEGY_REMOVE_ITEMS,
    ]);
    $this->gateway->save();
    static::assertEquals(PaytrailInterface::STRATEGY_REMOVE_ITEMS, $this->gateway->getPlugin()->orderDiscountStrategy());

    $order = $this
      ->setPricesIncludeTax(TRUE, ['FI'])
      ->createOrder();
    $order->addAdjustment(
      new Adjustment([
        'type' => 'custom',
        'label' => 'Discount',
        'amount' => new Price('-5', 'EUR'),
      ]));
    $order->save();

    $request = $this->getSut()->createPaymentRequest($order);
    // Make sure order item level discounts remove order items.
    $this->assertNull($request->getItems());
    // Make sure discount is still applied to total price.
    $this->assertEquals(1700, $request->getAmount());
  }

  /**
   * Tests billing profile.
   */
  public function testBillingProfile() : void {
    $order = $this->createOrder();
    $profile = Profile::create([
      'type' => 'customer',
      'uid' => $order->getCustomerId(),
    ]);
    $profile->set('address', [
      'country_code' => 'FI',
      'address_line1' => 'address 1',
      'postal_code' => '01800',
      'locality' => 'Klaukkala',
    ]);
    $profile->save();

    $order->setBillingProfile($profile)
      ->save();
    $request = $this->getSut()->createPaymentRequest($order);
    static::assertInstanceOf(Address::class, $request->getInvoicingAddress());
  }

  /**
   * @covers ::createHeaders
   * @covers ::get
   * @covers ::getResponse
   */
  public function testGet() : void {
    $mock = new MockHandler([
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

    $handlerStack = HandlerStack::create($mock);
    $client = new Client(['handler' => $handlerStack]);
    $this->container->set('http_client', $client);

    $order = $this->createOrder();
    $response = $this->getSut()->get('123', $order);
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
    $mock = new MockHandler([
      new Response(201, [
        // The signature is just copied from ::validateSignature().
        'signature' => '84a0d7a81958c74064a31046365ce344fc38d96e168541684d6ea6596463169b0bd34819e0bab4f56a8a2378a0cb728a55d5a5902c59584ae039482eb449c0a2',
      ],
        json_encode([])),
    ]);

    $handlerStack = HandlerStack::create($mock);
    $client = new Client(['handler' => $handlerStack]);
    $this->container->set('http_client', $client);

    $order = $this->createOrder();
    $response = $this->getSut()->create($order);
    static::assertInstanceOf(PaymentRequestResponse::class, $response);
    // Make sure event dispatcher was triggered for both, request and response.
    static::assertEquals(PaymentRequestBuilderInterface::PAYMENT_CREATE_EVENT, $this->caughtEvents[0]->event);
    static::assertEquals(PaymentRequestBuilderInterface::PAYMENT_CREATE_RESPONSE_EVENT, $this->caughtEvents[1]->event);
    static::assertCount(2, $this->caughtEvents);
  }

}
