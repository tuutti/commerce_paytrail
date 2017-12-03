<?php

namespace Drupal\Tests\commerce_paytrail\Kernel;

use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_order\Entity\OrderItem;
use Drupal\commerce_payment\Entity\PaymentGateway;
use Drupal\commerce_payment\Exception\PaymentGatewayException;
use Drupal\commerce_paytrail\Event\FormInterfaceEvent;
use Drupal\commerce_paytrail\PaymentManager;
use Drupal\commerce_paytrail\Plugin\Commerce\PaymentGateway\PaytrailBase;
use Drupal\commerce_paytrail\Repository\FormManager;
use Drupal\commerce_paytrail\Repository\Response;
use Drupal\commerce_price\Entity\Currency;
use Drupal\commerce_price\Price;
use Drupal\Tests\commerce\Kernel\CommerceKernelTestBase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;

/**
 * PaymentManager unit tests.
 *
 * @group commerce_paytrail
 * @coversDefaultClass \Drupal\commerce_paytrail\PaymentManager
 */
class PaymentManagerTest extends CommerceKernelTestBase {

  /**
   * The payment manager.
   *
   * @var \Drupal\commerce_paytrail\PaymentManager
   */
  protected $sut;

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $eventDispatcher;

  /**
   * The payment gateway.
   *
   * @var \Drupal\commerce_payment\Entity\PaymentGateway
   */
  protected $gateway;

  public static $modules = [
    'state_machine',
    'address',
    'profile',
    'entity_reference_revisions',
    'path',
    'commerce_product',
    'commerce_checkout',
    'commerce_order',
    'commerce_payment',
    'commerce_paytrail',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    $this->installEntitySchema('profile');
    $this->installEntitySchema('commerce_order');
    $this->installEntitySchema('commerce_order_item');
    $this->installEntitySchema('commerce_product');
    $this->installEntitySchema('commerce_product_variation');
    $this->installEntitySchema('commerce_payment');
    $this->installEntitySchema('commerce_payment_method');
    $this->installConfig('path');
    $this->installConfig('commerce_order');
    $this->installConfig('commerce_product');
    $this->installConfig('commerce_checkout');
    $this->installConfig('commerce_payment');
    $this->installConfig('commerce_paytrail');

    Currency::create([
      'currencyCode' => 'EUR',
      'name' => 'Euro',
    ])->save();

    $this->gateway = PaymentGateway::create(
      [
        'id' => 'paytrail',
        'label' => 'Paytrail',
        'plugin' => 'paytrail',
      ]
    );
    $this->gateway->getPlugin()->setConfiguration(
      [
        'culture' => 'automatic',
        'merchant_id' => '13466',
        'merchant_hash' => '6pKF4jkv97zmqBJ3ZL8gUw5DfT2NMQ',
        'bypass_mode' => FALSE,
        'included_data' => [
          PaytrailBase::PAYER_DETAILS => 0,
          PaytrailBase::PRODUCT_DETAILS => 0,
        ],
      ]
    );
    $this->gateway->save();

    $entityTypeManager = $this->container->get('entity_type.manager');
    $this->eventDispatcher = $this->getMock(EventDispatcherInterface::class);
    $time = $this->container->get('datetime.time');
    $moduleHandler = $this->container->get('module_handler');

    $this->sut = new PaymentManager($entityTypeManager, $this->eventDispatcher, $time, $moduleHandler);

    $account = $this->createUser([]);

    \Drupal::currentUser()->setAccount($account);
  }

  /**
   * Tests buildFormInterface().
   *
   * @covers ::buildFormInterface
   * @covers ::dispatch
   */
  public function testBuildFormInterface() {
    $orderItem = OrderItem::create([
      'type' => 'default',
    ]);
    $orderItem->setUnitPrice(new Price('11', 'EUR'));
    $order = Order::create([
      'type' => 'default',
      'store_id' => $this->store,
    ]);
    $order->addItem($orderItem);
    $order->save();

    $form = $this->sut->buildFormInterface($order, $this->gateway->getPlugin());
    $this->assertInstanceOf(FormManager::class, $form);

    $dispatched_event = new FormInterfaceEvent($this->gateway->getPlugin(), $order, $form);
    $this->eventDispatcher->expects($this->any())
      ->method('dispatch')
      ->willReturn($dispatched_event);

    $alter = $this->sut->dispatch($form, $this->gateway->getPlugin(), $order);
    $this->assertEquals('1', $alter['ORDER_NUMBER']);

    $authcode = $alter['AUTHCODE'];
    $this->assertNotEmpty($authcode);

    // Make sure we can alter elements.
    $dispatched_event->getFormInterface()->setOrderNumber('12345');
    $alter = $this->sut->dispatch($form, $this->gateway->getPlugin(), $order);
    $this->assertEquals('12345', $alter['ORDER_NUMBER']);
    // Make sure authcode gets regenerated when we change values.
    $this->assertNotEquals($alter['AUTHCODE'], $authcode);
  }

  /**
   * Tests payments.
   *
   * @covers ::getPayment
   * @covers ::createPaymentForOrder
   */
  public function testPayments() {
    $orderItem = OrderItem::create([
      'type' => 'default',
    ]);
    $orderItem->setUnitPrice(new Price('11', 'EUR'));
    $order = Order::create([
      'type' => 'default',
      'store_id' => $this->store,
    ]);
    $order->addItem($orderItem);
    $order->save();

    $request = Request::createFromGlobals();

    $request->query = new ParameterBag([
      'ORDER_NUMBER' => '123',
      'PAYMENT_ID' => '2333',
      'PAYMENT_METHOD' => '1',
      'TIMESTAMP' => 1512281966,
      'STATUS' => 'PAID',
      'RETURN_AUTHCODE' => '1234',
    ]);
    $response = Response::createFromRequest('1234', $order, $request);

    try {
      $this->sut->createPaymentForOrder('capture', $order, $this->gateway->getPlugin(), $response);
      $this->fail('Expected InvalidArgumentException');
    }
    catch (\InvalidArgumentException $e) {
      $this->assertEquals('Only payments in the "authorization" state can be captured.', $e->getMessage());
    }
    $payment = $this->sut->createPaymentForOrder('authorized', $order, $this->gateway->getPlugin(), $response);
    $this->assertEquals(1, $payment->id());
    $this->assertEquals('2333', $payment->getRemoteId());
    $this->assertEquals('authorization', $payment->getState()->value);

    $request->query->set('PAYMENT_ID', '23333');
    $response = Response::createFromRequest('1234', $order, $request);

    try {
      $this->sut->createPaymentForOrder('capture', $order, $this->gateway->getPlugin(), $response);
      $this->fail('Expected PaymentGatewayException');
    }
    catch (PaymentGatewayException $e) {
      $this->assertEquals('Remote id does not match with previously stored remote id.', $e->getMessage());
    }

    $request->query->set('PAYMENT_ID', '2333');
    $response = Response::createFromRequest('1234', $order, $request);
    $payment = $this->sut->createPaymentForOrder('capture', $order, $this->gateway->getPlugin(), $response);
    $this->assertEquals('completed', $payment->getState()->value);
  }

}
