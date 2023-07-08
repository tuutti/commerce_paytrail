<?php

declare(strict_types = 1);

namespace Drupal\Tests\commerce_paytrail\Kernel\Plugin\Commerce\PaymentGateway;

use Drupal\commerce_payment\Exception\PaymentGatewayException;
use Drupal\commerce_paytrail\RequestBuilder\PaymentRequestBuilderInterface;
use Drupal\Tests\commerce_paytrail\Kernel\RequestBuilderKernelTestBase;
use Paytrail\SDK\Response\PaymentStatusResponse;
use Prophecy\Argument;
use Symfony\Component\HttpFoundation\Response;

/**
 * Paytrail gateway plugin tests.
 *
 * @group commerce_paytrail
 * @coversDefaultClass \Drupal\commerce_paytrail\Plugin\Commerce\PaymentGateway\Paytrail
 */
class PaytrailPaymentTest extends RequestBuilderKernelTestBase {

  /**
   * Tests that payment fails when signature validation fails.
   *
   * @covers ::onReturn
   * @covers ::onNotify
   * @covers ::onNotifySuccess
   * @covers ::validateResponse
   * @covers \Drupal\commerce_paytrail\RequestBuilder\PaymentRequestBuilder::get
   */
  public function testSignatureValidationFailed() : void {
    $builder = $this->prophesize(PaymentRequestBuilderInterface::class);
    $gateway = $this->mockPaymentGateway($builder->reveal());
    $sut = $gateway->getPlugin();
    $order = $this->createOrder($gateway);
    $request = $this->createRequest($sut, [
      'checkout-reference' => $order->id(),
      'checkout-transaction-id' => '123',
      'checkout-stamp' => '123',
    ]);
    $request->query->set('signature', '123');

    $response = $sut
      ->onNotify($request);
    static::assertEquals(403, $response->getStatusCode());
    static::assertEquals('Signature does not match.', $response->getContent());

    $this->expectException(PaymentGatewayException::class);
    $this->expectExceptionMessage('Signature does not match.');
    $sut->onReturn($order, $request);
  }

  /**
   * @covers ::onReturn
   * @covers ::onNotify
   * @covers ::onNotifySuccess
   * @covers ::validateResponse
   * @covers \Drupal\commerce_paytrail\RequestBuilder\PaymentRequestBuilder::get
   */
  public function testTransactionIdNotSetException() : void {
    $builder = $this->prophesize(PaymentRequestBuilderInterface::class);
    $gateway = $this->mockPaymentGateway($builder->reveal());
    $sut = $gateway->getPlugin();
    $order = $this->createOrder($gateway);
    $request = $this->createRequest($sut, [
      'checkout-reference' => $order->id(),
      'checkout-transaction-id' => NULL,
      'checkout-stamp' => '123',
    ]);

    $response = $sut
      ->onNotify($request);
    static::assertEquals(403, $response->getStatusCode());
    static::assertEquals('Transaction ID not set.', $response->getContent());

    $this->expectException(PaymentGatewayException::class);
    $this->expectExceptionMessage('Transaction ID not set.');
    $sut->onReturn($order, $request);
  }

  /**
   * Tests that order id must match the checkout-reference query parameter.
   *
   * @covers ::onReturn
   * @covers ::onNotify
   * @covers ::onNotifySuccess
   * @covers ::validateResponse
   */
  public function testInvalidOrderId() : void {
    foreach ([55, NULL] as $orderId) {
      $builder = $this->prophesize(PaymentRequestBuilderInterface::class);
      $gateway = $this->mockPaymentGateway($builder->reveal());
      $sut = $gateway->getPlugin();
      $order = $this->createOrder($gateway);

      $response = $sut
        ->onNotify($this->createRequest($sut, [
          'checkout-reference' => $orderId,
          'checkout-transaction-id' => '123',
          'checkout-stamp' => '123',
        ]));
      static::assertEquals(403, $response->getStatusCode());
      static::assertEquals('Order not found.', $response->getContent());

      $this->expectException(PaymentGatewayException::class);
      $this->expectExceptionMessage('Order ID mismatch.');
      $sut->onReturn($order, $this->createRequest($sut, [
        'checkout-reference' => $orderId,
        'checkout-transaction-id' => '123',
        'checkout-stamp' => '123',
      ]));
    }
  }

  /**
   * Tests that payment fails when remote state is not correct.
   *
   * @covers ::onReturn
   * @covers ::onNotify
   * @covers ::onNotifySuccess
   * @covers ::validateResponse
   */
  public function testPaymentInvalidResponseStatus() : void {
    $builder = $this->prophesize(PaymentRequestBuilderInterface::class);
    $builder
      ->get(Argument::any(), Argument::any())
      ->shouldBeCalled()
      ->willReturn(
        (new PaymentStatusResponse())
          ->setStatus('fail')
      );
    $gateway = $this->mockPaymentGateway($builder->reveal());
    $order = $this->createOrder($gateway);
    $sut = $gateway->getPlugin();
    $request = $this->createRequest($sut, [
      'checkout-reference' => $order->id(),
      'checkout-transaction-id' => '123',
      'checkout-stamp' => '123',
    ]);

    $response = $sut->onNotify($request);
    static::assertEquals(Response::HTTP_FORBIDDEN, $response->getStatusCode());
    static::assertStringStartsWith('Invalid status: fail', $response->getContent());

    $this->expectException(PaymentGatewayException::class);
    $this->expectExceptionMessage('Invalid status: fail');
    $sut->onReturn($order, $request);
  }

  /**
   * Tests that payment can be fully captured.
   *
   * @covers ::onReturn
   * @covers ::handlePayment
   * @covers ::validateResponse
   * @covers ::onNotifySuccess
   * @covers \Drupal\commerce_paytrail\RequestBuilder\PaymentRequestBuilder::get
   */
  public function testPaymentCapture() : void {
    $builder = $this->prophesize(PaymentRequestBuilderInterface::class);
    $builder
      ->get(Argument::any(), Argument::any())
      ->shouldBeCalled()
      ->willReturn(
        (new PaymentStatusResponse())
          ->setStatus('ok')
          ->setTransactionId('123')
      );

    $gateway = $this->mockPaymentGateway($builder->reveal());
    $order = $this->createOrder($gateway);
    $sut = $gateway->getPlugin();
    $request = $this->createRequest($sut, [
      'checkout-reference' => $order->id(),
      'checkout-transaction-id' => '123',
      'checkout-stamp' => '123',
    ]);
    $sut->onReturn($order, $request);

    $payment = $this->loadPayment('123');
    static::assertEquals('completed', $payment->getState()->getId());
    static::assertEquals('ok', $payment->getRemoteState());
    static::assertEquals('22', $payment->getAmount()->getNumber());
  }

  /**
   * Tests that payment can be fully captured via onNotify().
   *
   * @covers ::onNotify
   * @covers ::handlePayment
   * @covers ::onNotifySuccess
   * @covers ::validateResponse
   * @covers \Drupal\commerce_paytrail\RequestBuilder\PaymentRequestBuilder::get
   */
  public function testOnNotifyPaymentCapture() : void {
    $builder = $this->prophesize(PaymentRequestBuilderInterface::class);
    $builder
      ->get(Argument::any(), Argument::any())
      ->shouldBeCalled()
      ->willReturn(
        (new PaymentStatusResponse())
          ->setStatus('ok')
          ->setTransactionId('123')
      );
    $gateway = $this->mockPaymentGateway($builder->reveal());
    $sut = $gateway->getPlugin();
    $order = $this->createOrder($gateway);
    $sut->onNotify($this->createRequest($sut, [
      'checkout-reference' => $order->id(),
      'checkout-transaction-id' => '123',
      'checkout-stamp' => '123',
    ]));

    $payment = $this->loadPayment('123');
    static::assertEquals('completed', $payment->getState()->getId());
    static::assertEquals('ok', $payment->getRemoteState());
    static::assertEquals('22', $payment->getAmount()->getNumber());
  }

}
