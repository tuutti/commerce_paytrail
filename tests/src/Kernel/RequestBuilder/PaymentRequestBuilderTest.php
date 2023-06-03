<?php

declare(strict_types = 1);

namespace Drupal\Tests\commerce_paytrail\Kernel\RequestBuilder;

use Drupal\commerce_order\Adjustment;
use Drupal\commerce_paytrail\Plugin\Commerce\PaymentGateway\PaytrailBase;
use Drupal\commerce_paytrail\RequestBuilder\PaymentRequestBuilder;
use Drupal\commerce_price\Price;
use Drupal\profile\Entity\Profile;
use Drupal\Tests\commerce_paytrail\Kernel\RequestBuilderKernelTestBase;
use Paytrail\Payment\Model\Address;
use Paytrail\Payment\Model\PaymentRequest;

/**
 * Tests Payment requests.
 *
 * @group commerce_paytrail
 * @coversDefaultClass \Drupal\commerce_paytrail\RequestBuilder\PaymentRequestBuilder
 */
class PaymentRequestBuilderTest extends RequestBuilderKernelTestBase {

  /**
   * The payment request builder.
   *
   * @var \Drupal\commerce_paytrail\RequestBuilder\PaymentRequestBuilder
   */
  protected ?PaymentRequestBuilder $sut;

  /**
   * {@inheritdoc}
   */
  protected function setUp() : void {
    parent::setUp();
    $this->sut = $this->container->get('commerce_paytrail.payment_request');
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
  public function testCreate() : void {
    $order = $this->createOrder();

    $request = $this->sut->createPaymentRequest($order);
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
  public function testCreatePricesIncludeTax() : void {
    $order = $this
      ->setPricesIncludeTax(TRUE, ['FI'])
      ->createOrder();

    $request = $this->sut->createPaymentRequest($order);
    // Order should have prices included in unit prices.
    $this->assertTaxes($request, 2200, 1100, 24);
  }

  /**
   * Make sure taxes are added to total price.
   */
  public function testCreatePricesIncludeNoTax() : void {
    $order = $this
      ->setPricesIncludeTax(FALSE, ['FI'])
      ->createOrder();

    $request = $this->sut->createPaymentRequest($order);
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
    $request = $this->sut->createPaymentRequest($order);
    // Make sure order items are not removed.
    $this->assertNotNull($request->getItems());

    $this->assertTaxes($request, 1700, 850, 24);
  }

  /**
   * Make sure order level discounts remove items if configured so.
   */
  public function testOrderLevelDiscount() : void {
    $this->gateway->getPlugin()->setConfiguration([
      'order_discount_strategy' => PaytrailBase::STRATEGY_REMOVE_ITEMS,
    ]);
    $this->gateway->save();
    $this->assertEquals(PaytrailBase::STRATEGY_REMOVE_ITEMS, $this->gateway->getPlugin()->orderDiscountStrategy());

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

    $request = $this->sut->createPaymentRequest($order);
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
    $request = $this->sut->createPaymentRequest($order);
    static::assertInstanceOf(Address::class, $request->getInvoicingAddress());
  }

  /**
   * Make sure we can subscribe to model events.
   */
  public function testEventSubscriberEvent() : void {
    $this->assertCaughtEvents(1, function () {
      $this->sut->createPaymentRequest($this->createOrder());
    });
  }

}
