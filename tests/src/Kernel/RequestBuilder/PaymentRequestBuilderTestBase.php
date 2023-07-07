<?php

declare(strict_types = 1);

namespace Drupal\Tests\commerce_paytrail\Kernel\RequestBuilder;

use Drupal\commerce_order\Adjustment;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_paytrail\Plugin\Commerce\PaymentGateway\PaytrailInterface;
use Drupal\commerce_price\Price;
use Drupal\profile\Entity\Profile;
use Drupal\Tests\commerce_paytrail\Kernel\RequestBuilderKernelTestBase;
use Paytrail\SDK\Model\Address;
use Paytrail\SDK\Request\AbstractPaymentRequest;

/**
 * A base class for payment request tests.
 */
abstract class PaymentRequestBuilderTestBase extends RequestBuilderKernelTestBase {

  /**
   * Asserts order taxes.
   *
   * @param \Paytrail\SDK\Request\AbstractPaymentRequest $request
   *   The request to validate.
   * @param int $expectedTotalPrice
   *   The expected total price.
   * @param int $expectedUnitPrice
   *   The expected unit price.
   * @param int $expectedVatPercentage
   *   The expected vat percentage.
   */
  protected function assertTaxes(
    AbstractPaymentRequest $request,
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
   * Gets the request used to test shared functionality.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order.
   *
   * @return \Paytrail\SDK\Request\AbstractPaymentRequest
   *   The request.
   */
  abstract protected function getRequest(OrderInterface $order) : AbstractPaymentRequest;

  /**
   * Make sure order has no taxes by default.
   *
   * @covers \Drupal\commerce_paytrail\RequestBuilder\PaymentRequestBase::populatePaymentRequest
   */
  public function testPaymentRequest() : void {
    $order = $this->createOrder($this->createGatewayPlugin());

    $request = $this->getRequest($order);
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
      ->createOrder($this->createGatewayPlugin());

    $request = $this->getRequest($order);
    // Order should have prices included in unit prices.
    $this->assertTaxes($request, 2200, 1100, 24);
  }

  /**
   * Make sure taxes are added to total price.
   */
  public function testPricesIncludeNoTax() : void {
    $order = $this
      ->setPricesIncludeTax(FALSE, ['FI'])
      ->createOrder($this->createGatewayPlugin());

    $request = $this->getRequest($order);
    // Taxes should be added to unit price.
    $this->assertTaxes($request, 2728, 1364, 24);
  }

  /**
   * Make sure discounts are included.
   */
  public function testDiscount() : void {
    $order = $this
      ->setPricesIncludeTax(TRUE, ['FI'])
      ->createOrder($this->createGatewayPlugin(), [
        new Adjustment([
          'type' => 'custom',
          'label' => 'Discount',
          'amount' => new Price('-5', 'EUR'),
        ]),
      ]);
    $request = $this->getRequest($order);
    // Make sure order items are not removed.
    static::assertNotNull($request->getItems());

    $this->assertTaxes($request, 1700, 850, 24);
  }

  /**
   * Make sure order level discounts remove items if configured so.
   */
  public function testOrderLevelDiscount() : void {
    $gateway = $this->createGatewayPlugin();
    $gateway->getPlugin()->setConfiguration([
      'order_discount_strategy' => PaytrailInterface::STRATEGY_REMOVE_ITEMS,
    ]);
    $gateway->save();
    static::assertEquals(PaytrailInterface::STRATEGY_REMOVE_ITEMS, $gateway->getPlugin()->orderDiscountStrategy());

    $order = $this
      ->setPricesIncludeTax(TRUE, ['FI'])
      ->createOrder($gateway);
    $order->addAdjustment(
      new Adjustment([
        'type' => 'custom',
        'label' => 'Discount',
        'amount' => new Price('-5', 'EUR'),
      ]));
    $order->save();

    $request = $this->getRequest($order);
    // Make sure order item level discounts remove order items.
    static::assertNull($request->getItems());
    // Make sure discount is still applied to total price.
    static::assertEquals(1700, $request->getAmount());
  }

  /**
   * Tests billing profile.
   */
  public function testBillingProfile() : void {
    $order = $this->createOrder($this->createGatewayPlugin());
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
    $request = $this->getRequest($order);
    static::assertInstanceOf(Address::class, $request->getInvoicingAddress());
  }

}
