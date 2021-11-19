<?php

declare(strict_types = 1);

namespace Drupal\Tests\commerce_paytrail\Kernel;

use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\Entity\OrderItem;
use Drupal\commerce_paytrail\RequestBuilder\PaymentRequestBuilder;
use Drupal\commerce_price\Price;
use Drupal\commerce_tax\Entity\TaxType;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Uuid\UuidInterface;
use Drupal\Tests\commerce_paytrail\Traits\ApiTestTrait;
use GuzzleHttp\Psr7\Response;
use Paytrail\Payment\Model\PaymentRequestResponse;

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

  /**
   * Tests ::create().
   */
  public function testCreate() : void {
    $expectedStamp = '37cd1507-c5be-4102-9855-2a26387fac7f';

    $uuid = $this->getMockBuilder(UuidInterface::class)
      ->getMock();
    $uuid
      ->method('generate')
      ->willReturn($expectedStamp);

    $time = $this->getMockBuilder(TimeInterface::class)
      ->getMock();
    $time->method('getCurrentTime')
      ->willReturn('1637332706');

    $expectedTransactionId = '5770642a-9a02-4ca2-8eaa-cc6260a78eb6';

    $order = $this->createOrder();

    $headers = [
      'checkout-account' => ['375917'],
      'checkout-method' => ['POST'],
      'checkout-algorithm' => ['sha512'],
      'checkout-transaction-id' => [$expectedTransactionId],
      // Pre-calculated signature.
      'signature' => '023f3659ad6a1abb71351793b561df1e89f004084768d5702d6037a059c0c45154871756a77b5ddffd61acc516d74981aa7fd4cf414f97d94c1f9df084a48b4b',
    ];
    $body = [
      'transactionId' => $expectedTransactionId,
      'href' => 'https://services.paytrail.com/pay/5770642a-9a02-4ca2-8eaa-cc6260a78eb6',
      'reference' => $order->id(),
      'groups' => [
        [
          'id' => 'mobile',
          'name' => 'Mobile payment methods',
        ],
      ],
      'providers' => [
        [
          'url' => 'https://maksu.pivo.fi/api/payments',
          'parameters' => [
            [
              'name' => 'amount',
              'value' => 'base64 MTUyNQ==',
            ],
          ],
        ],
      ],
    ];
    $client = $this->createMockHttpClient([
      new Response(201, $headers, json_encode($body)),
    ]);

    $sut = new PaymentRequestBuilder(
      $this->container->get('event_dispatcher'),
      $client,
      $uuid,
      $time,
      $this->container->get('commerce_price.minor_units_converter')
    );
    $response = $sut->create($order);
    $this->assertInstanceOf(PaymentRequestResponse::class, $response);
    $this->assertEquals($expectedTransactionId, $order->getData(PaymentRequestBuilder::TRANSACTION_ID_KEY));
    $this->assertEquals($expectedStamp, $order->getData(PaymentRequestBuilder::STAMP_KEY));
  }

}
