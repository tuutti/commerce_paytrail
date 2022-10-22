<?php

declare(strict_types = 1);

namespace Drupal\Tests\commerce_paytrail\Kernel;

use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\Entity\OrderItem;
use Drupal\commerce_payment\Entity\PaymentGateway;
use Drupal\commerce_paytrail\RequestBuilder\PaymentRequestBuilder;
use Drupal\commerce_price\Price;
use Drupal\commerce_product\Entity\ProductVariation;
use Drupal\commerce_shipping\Entity\ShippingMethod;
use Drupal\commerce_tax\Entity\TaxType;
use Drupal\physical\Weight;
use Drupal\profile\Entity\Profile;
use Drupal\Tests\commerce_shipping\Kernel\ShippingKernelTestBase;

/**
 * Tests Payment requests with shipping.
 *
 * @group commerce_paytrail
 * @coversDefaultClass \Drupal\commerce_paytrail\Commerce\Shipping\ShippingEventSubscriber
 */
class PaymentRequestBuilderShippingTest extends ShippingKernelTestBase {

  /**
   * The payment request builder.
   *
   * @var \Drupal\commerce_paytrail\RequestBuilder\PaymentRequestBuilder
   */
  protected ?PaymentRequestBuilder $sut;

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

    $this->installConfig(['commerce_tax', 'commerce_checkout']);
    $this->installEntitySchema('commerce_payment_method');

    $this->store = $this->createStore(country: 'FI', currency: 'EUR');
    $this->sut = $this->container->get('commerce_paytrail.payment_request');
  }

  /**
   * Setup order object.
   *
   * @param bool $applyTaxes
   *   Whether to apply taxes or not.
   */
  private function setupOrder(bool $applyTaxes, bool $includeTaxes) : OrderInterface {
    $gateway = PaymentGateway::create([
      'id' => 'paytrail',
      'label' => 'Paytrail',
      'plugin' => 'paytrail',
    ]);
    $gateway->save();

    $this->store->set('prices_include_tax', $includeTaxes);
    if ($applyTaxes) {
      $this->store->set('tax_registrations', ['FI']);
    }
    $this->store->save();

    $eu_tax_type = TaxType::create([
      'id' => 'eu_vat',
      'label' => 'EU VAT',
      'plugin' => 'european_union_vat',
      'configuration' => [
        'display_inclusive' => TRUE,
      ],
    ]);
    $eu_tax_type->save();

    $first_variation = ProductVariation::create([
      'type' => 'default',
      'sku' => 'test-product-01',
      'title' => 'Hat',
      'price' => new Price('70.00', 'EUR'),
      'weight' => new Weight('0', 'g'),
    ]);
    $first_variation->save();

    $first_order_item = OrderItem::create([
      'type' => 'default',
      'quantity' => 1,
      'title' => $first_variation->getOrderItemTitle(),
      'purchased_entity' => $first_variation,
      'unit_price' => new Price('70.00', 'EUR'),
    ]);
    $first_order_item->save();

    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $order = Order::create([
      'type' => 'default',
      'uid' => $this->createUser(['mail' => $this->randomString() . '@example.com']),
      'store_id' => $this->store->id(),
      'payment_gateway' => $gateway,
      'order_items' => [
        $first_order_item,
      ],
    ]);
    $order->save();

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
   * @covers ::addShipping
   * @covers ::getSubscribedEvents
   */
  public function testShipment() : void {
    $order = $this->setupOrder(FALSE, FALSE);
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
   * @covers ::addShipping
   * @covers ::getSubscribedEvents
   */
  public function testShipmentIncludeTaxes() : void {
    $order = $this->setupOrder(TRUE, TRUE);
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
   * @covers ::addShipping
   * @covers ::getSubscribedEvents
   */
  public function testShipmentIncludeNoTaxes() : void {
    $order = $this->setupOrder(TRUE, FALSE);
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
