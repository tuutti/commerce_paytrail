<?php

declare(strict_types = 1);

namespace Drupal\Tests\commerce_paytrail\Kernel\RequestBuilder;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_payment\Entity\PaymentGatewayInterface;
use Drupal\commerce_paytrail\RequestBuilder\PaymentRequestBuilder;
use Drupal\commerce_price\Price;
use Drupal\commerce_shipping\Entity\ShippingMethod;
use Drupal\commerce_tax\Entity\TaxType;
use Drupal\profile\Entity\Profile;
use Drupal\Tests\commerce_paytrail\Traits\ApiTestTrait;
use Drupal\Tests\commerce_paytrail\Traits\OrderTestTrait;
use Drupal\Tests\commerce_paytrail\Traits\TaxTestTrait;
use Drupal\Tests\commerce_shipping\Kernel\ShippingKernelTestBase;

/**
 * Tests Payment requests with shipping.
 *
 * @group commerce_paytrail
 * @coversDefaultClass \Drupal\commerce_paytrail\Commerce\Shipping\ShippingEventSubscriber
 */
class PaymentRequestBuilderShippingTest extends ShippingKernelTestBase {

  use ApiTestTrait;
  use OrderTestTrait;
  use TaxTestTrait;

  /**
   * The payment request builder.
   *
   * @var \Drupal\commerce_paytrail\RequestBuilder\PaymentRequestBuilder
   */
  protected ?PaymentRequestBuilder $sut;

  /**
   * The payment gateway.
   *
   * @var \Drupal\commerce_payment\Entity\PaymentGateway
   */
  protected ?PaymentGatewayInterface $gateway;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'commerce_tax',
    'commerce_checkout',
    'commerce_payment',
    'commerce_paytrail',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig(['commerce_checkout']);
    $this->installEntitySchema('commerce_payment_method');

    $this->setupTaxes();
    $this->store = $this->createStore(country: 'FI', currency: 'EUR');
    $this->gateway = $this->createGatewayPlugin();
    $this->sut = $this->container->get('commerce_paytrail.payment_request');
  }

  /**
   * Setup order object.
   *
   * @return \Drupal\commerce_order\Entity\OrderInterface
   *   The order.
   */
  private function createShippingOrder() : OrderInterface {
    TaxType::create([
      'id' => 'shipping',
      'label' => 'Shipping',
      'plugin' => 'shipping',
      'configuration' => [
        'strategy' => 'default',
      ],
    ])->save();

    $shipping_method = ShippingMethod::create([
      'stores' => $this->store->id(),
      'name' => 'Standard shipping',
      'plugin' => [
        'target_plugin_id' => 'flat_rate',
        'target_plugin_configuration' => [
          'rate_label' => 'Standard shipping',
          'rate_amount' => [
            'number' => '10.00',
            'currency_code' => 'EUR',
          ],
        ],
      ],
      'status' => TRUE,
    ]);
    $shipping_method->save();

    $order = $this->createOrder();

    /** @var \Drupal\profile\Entity\ProfileInterface $shipping_profile */
    $shipping_profile = Profile::create([
      'type' => 'customer',
      'address' => [
        'country_code' => 'FI',
      ],
    ]);
    $shipping_profile->save();

    $shipping_order_manager = $this->container->get('commerce_shipping.order_manager');
    /** @var \Drupal\commerce_shipping\Entity\ShipmentInterface[] $shipments */
    $shipments = $shipping_order_manager->pack($order, $shipping_profile);
    $shipment = reset($shipments);
    $shipment->setShippingMethodId($shipping_method->id());
    $shipment->setShippingService('default');
    $shipment->setAmount(new Price('10.00', 'EUR'));
    $order->set('shipments', [$shipment]);
    $order->save();
    return $order;
  }

  /**
   * Tests shipment without taxes.
   *
   * @covers ::processEvent
   * @covers ::isValid
   * @covers ::getSubscribedEvents
   * @covers \Drupal\commerce_paytrail\CommercePaytrailServiceProvider::register
   */
  public function testShipmentWithoutTaxes() : void {
    $order = $this
      ->setPricesIncludeTax(FALSE)
      ->createShippingOrder();
    $items = $this->sut->createPaymentRequest($order)->getItems();

    /** @var \Paytrail\Payment\Model\Item $shippingItem */
    $shippingItem = end($items);
    static::assertEquals(0, $shippingItem->getVatPercentage());
    static::assertEquals(1000, $shippingItem->getUnitPrice());
    static::assertEquals('flat_rate', $shippingItem->getProductCode());
  }

  /**
   * Tests shipment with taxes included.
   *
   * @covers ::processEvent
   * @covers ::isValid
   * @covers ::getSubscribedEvents
   * @covers \Drupal\commerce_paytrail\CommercePaytrailServiceProvider::register
   */
  public function testShipmentIncludeTaxes() : void {
    $order = $this
      ->setPricesIncludeTax(TRUE, ['FI'])
      ->createShippingOrder();
    $items = $this->sut->createPaymentRequest($order)->getItems();

    /** @var \Paytrail\Payment\Model\Item $shippingItem */
    $shippingItem = end($items);
    static::assertEquals(24, $shippingItem->getVatPercentage());
    static::assertEquals(1000, $shippingItem->getUnitPrice());
    static::assertEquals('flat_rate', $shippingItem->getProductCode());
  }

  /**
   * Tests taxes when taxes are not included in prices.
   *
   * @covers ::processEvent
   * @covers ::isValid
   * @covers ::getSubscribedEvents
   * @covers \Drupal\commerce_paytrail\CommercePaytrailServiceProvider::register
   */
  public function testShipmentIncludeNoTaxes() : void {
    $order = $this
      ->setPricesIncludeTax(FALSE, ['FI'])
      ->createShippingOrder();
    $items = $this->sut->createPaymentRequest($order)->getItems();

    /** @var \Paytrail\Payment\Model\Item $shippingItem */
    $shippingItem = end($items);
    static::assertEquals(24, $shippingItem->getVatPercentage());
    // @todo commerce_shipping doesn't respect store's tax setting at the moment.
    // Fix the unit price if this is ever fixed.
    // @see https://www.drupal.org/project/commerce_shipping/issues/3189727
    static::assertEquals(1000, $shippingItem->getUnitPrice());
    static::assertEquals('flat_rate', $shippingItem->getProductCode());
  }

}
