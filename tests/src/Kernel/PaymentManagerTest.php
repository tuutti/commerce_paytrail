<?php

namespace Drupal\Tests\commerce_paytrail\Kernel;

use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_order\Entity\OrderItem;
use Drupal\commerce_payment\Exception\PaymentGatewayException;
use Drupal\commerce_paytrail\Repository\FormManager;
use Drupal\commerce_paytrail\Repository\Response;
use Drupal\commerce_price\Price;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;

/**
 * PaymentManager unit tests.
 *
 * @group commerce_paytrail
 * @coversDefaultClass \Drupal\commerce_paytrail\PaymentManager
 */
class PaymentManagerTest extends PaymentManagerKernelTestBase {

  /**
   * Tests ::getReturnUrl().
   *
   * @covers ::getReturnUrl
   * @dataProvider returnUrlDataProvider
   */
  public function testReturnUrl(string $expected, string $route, array $arguments) {
    $order = Order::create([
      'type' => 'default',
      'store_id' => $this->store,
    ]);
    $order->save();

    $value = $this->sut->getReturnUrl($order, $route, $arguments);
    $expected = str_replace('%id', $order->id(), $expected);

    $this->assertEquals($expected, $value);
  }

  /**
   * Data provider for testReturnUrl().
   */
  public function returnUrlDataProvider() {
    return [
      [
        'http://localhost/checkout/%id/payment/return',
        'commerce_payment.checkout.return',
        [],
      ],
      [
        'http://localhost/checkout/%id/payment/cancel',
        'commerce_payment.checkout.cancel',
        [],
      ],
      [
        'http://localhost/checkout/%id/review/cancel',
        'commerce_payment.checkout.cancel',
        ['step' => 'review'],
      ],
      [
        'http://localhost/payment/notify/paytrail?commerce_order=%id&step=payment',
        'commerce_payment.notify',
        ['commerce_payment_gateway' => 'paytrail'],
      ],
      [
        'http://localhost/payment/notify/test?commerce_order=%id&step=payment',
        'commerce_payment.notify',
        ['commerce_payment_gateway' => 'test'],
      ],
    ];
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

    $alter = $this->sut->dispatch($form, $this->gateway->getPlugin(), $order);
    $this->assertEquals('1', $alter['ORDER_NUMBER']);

    $authcode = $alter['AUTHCODE'];
    $this->assertNotEmpty($authcode);
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

  /**
   * @covers ::createPaymentForOrder
   * @covers ::getPayment
   */
  public function testIpnPayment() {
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

    $this->gateway->getPlugin()->setConfiguration(
      [
        'allow_ipn_create_payment' => TRUE,
      ]
    );
    $this->gateway->save();

    $payment = $this->sut->createPaymentForOrder('capture', $order, $this->gateway->getPlugin(), $response);
    $this->assertEquals('completed', $payment->getState()->value);
  }

}
