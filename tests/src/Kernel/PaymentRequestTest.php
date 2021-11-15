<?php

declare(strict_types = 1);

namespace Drupal\Tests\commerce_paytrail\Kernel;

use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\Entity\OrderItem;
use Drupal\commerce_paytrail\RequestBuilder\PaymentRequestBuilder;
use Drupal\commerce_price\Price;
use Drupal\commerce_tax\Entity\TaxType;
use Drupal\Tests\commerce_paytrail\Traits\ApiTestTrait;
use GuzzleHttp\Psr7\Response;
use Paytrail\Payment\Model\PaymentRequestResponse;
use Paytrail\Payment\ObjectSerializer;

/**
 * Tests Payment requests.
 */
class PaymentRequestTest extends PaytrailKernelTestBase {

  use ApiTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'state_machine',
    'profile',
    'entity_reference_revisions',
    'path',
    'commerce_tax',
    'commerce_product',
    'commerce_checkout',
    'commerce_order',
    'commerce_payment',
    'commerce_promotion',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() : void {
    parent::setUp();

    $this->installEntitySchema('profile');
    $this->installEntitySchema('commerce_order');
    $this->installEntitySchema('commerce_order_item');
    $this->installEntitySchema('commerce_product');
    $this->installEntitySchema('commerce_product_variation');
    $this->installEntitySchema('commerce_payment');
    $this->installEntitySchema('commerce_payment_method');
    $this->installEntitySchema('commerce_promotion');
    $this->installConfig('path');
    $this->installConfig('commerce_order');
    $this->installConfig('commerce_tax');
    $this->installConfig('commerce_product');
    $this->installConfig('commerce_checkout');
    $this->installConfig('commerce_payment');
    $this->installConfig('commerce_promotion');
    $this->installConfig('commerce_paytrail');

    TaxType::create([
      'id' => 'vat',
      'label' => 'VAT',
      'plugin' => 'european_union_vat',
      'configuration' => [
        'display_inclusive' => TRUE,
      ],
    ])->save();

    $this->store->set('prices_include_tax', TRUE)->save();
    $account = $this->createUser([]);

    \Drupal::currentUser()->setAccount($account);
  }

  /**
   * Creates new order.
   *
   * @param \Drupal\commerce_order\Adjustment[] $itemAdjustments
   *   The order item adjustments.
   *
   * @return \Drupal\commerce_order\Entity\OrderInterface
   *   The order.
   */
  protected function createOrder(array $itemAdjustments = []): OrderInterface {
    /** @var \Drupal\commerce_order\Entity\OrderItemInterface $orderItem */
    $orderItem = OrderItem::create([
      'type' => 'default',
    ]);
    $orderItem->setUnitPrice(new Price('11', 'EUR'))
      ->setTitle('Title')
      ->setQuantity(2);

    if ($itemAdjustments) {
      foreach ($itemAdjustments as $adjustment) {
        $orderItem->addAdjustment($adjustment);
      }
    }
    $orderItem->save();

    $order = Order::create([
      'type' => 'default',
      'store_id' => $this->store,
      'payment_gateway' => $this->gateway,
    ]);
    $order->addItem($orderItem);
    $order->save();

    return $order;
  }

  public function testCreateNoTaxes() : void {
    $client = $this->createMockHttpClient([
      new Response(200, ''),
      new Response(200, body: '{"transactionId":"ab4713c2-3a37-11ec-a94f-cbbf734f44ee","status":"ok","amount":12300,"currency":"EUR","reference":"124","stamp":"3cbc14b8-5c50-4cfb-a5c0-7ed398ef77c2","createdAt":"2021-10-31T10:45:31.257Z","provider":"osuuspankki","filingCode":"202110315934970000","paidAt":"2021-10-31T10:45:43.366Z"}'),
    ]);

    $sut = new PaymentRequestBuilder(
      $this->container->get('event_dispatcher'),
      $client,
      $this->container->get('uuid'),
      $this->container->get('commerce_price.minor_units_converter')
    );
    $response = $sut->create($this->createOrder());
    $this->assertInstanceOf(PaymentRequestResponse::class, $response);
  }

}
