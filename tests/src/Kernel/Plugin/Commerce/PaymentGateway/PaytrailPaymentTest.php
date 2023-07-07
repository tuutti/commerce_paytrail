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
   * @covers ::validateResponse
   * @covers \Drupal\commerce_paytrail\RequestBuilder\PaymentRequestBuilder::get
   */
  public function testSignatureValidationFailed() : void {
    $builder = $this->prophesize(PaymentRequestBuilderInterface::class);
    $order = $this->createOrder($this->createGatewayPlugin());
    $sut = $this->mockPaymentGatewayPlugin($builder->reveal());
    $request = $this->createRequest($order->id(), $sut);
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
   * @covers ::validateResponse
   * @covers \Drupal\commerce_paytrail\RequestBuilder\PaymentRequestBuilder::get
   */
  public function testTransactionIdNotSetException() : void {
    $builder = $this->prophesize(PaymentRequestBuilderInterface::class);
    $order = $this->createOrder($this->createGatewayPlugin());
    $sut = $this->mockPaymentGatewayPlugin($builder->reveal());
    $request = $this->createRequest($order->id(), $sut);
    $request->query->set('checkout-transaction-id', NULL);

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
   * @covers ::validateResponse
   */
  public function testInvalidOrderId() : void {
    $builder = $this->prophesize(PaymentRequestBuilderInterface::class);
    $order = $this->createOrder($this->createGatewayPlugin());
    $sut = $this->mockPaymentGatewayPlugin($builder->reveal());

    $response = $sut
      ->onNotify($this->createRequest(55, $sut));
    static::assertEquals(403, $response->getStatusCode());
    static::assertEquals('Order not found.', $response->getContent());

    $this->expectException(PaymentGatewayException::class);
    $this->expectExceptionMessage('Order ID mismatch.');
    $sut->onReturn($order, $this->createRequest('non-existent-order', $sut));
  }

  /**
   * Tests that payment fails when remote state is not correct.
   *
   * @covers ::onReturn
   * @covers ::onNotify
   * @covers ::validateResponse
   */
  public function testPaymentInvalidResponseStatus() : void {
    $order = $this->createOrder($this->createGatewayPlugin());
    $builder = $this->prophesize(PaymentRequestBuilderInterface::class);
    $builder
      ->get(Argument::any(), Argument::any())
      ->shouldBeCalled()
      ->willReturn(
        (new PaymentStatusResponse())
          ->setStatus('fail')
      );
    $sut = $this->mockPaymentGatewayPlugin($builder->reveal());
    $request = $this->createRequest($order->id(), $sut);

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
   * @covers \Drupal\commerce_paytrail\RequestBuilder\PaymentRequestBuilder::get
   */
  public function testPaymentCapture() : void {
    $order = $this->createOrder($this->createGatewayPlugin());
    $builder = $this->prophesize(PaymentRequestBuilderInterface::class);
    $builder
      ->get(Argument::any(), Argument::any())
      ->shouldBeCalled()
      ->willReturn(
        (new PaymentStatusResponse())
          ->setStatus('ok')
          ->setTransactionId('123')
      );

    $plugin = $this->mockPaymentGatewayPlugin($builder->reveal());
    $request = $this->createRequest($order->id(), $plugin);
    $plugin->onReturn($order, $request);

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
   * @covers ::validateResponse
   * @covers \Drupal\commerce_paytrail\RequestBuilder\PaymentRequestBuilder::get
   */
  public function testOnNotifyPaymentCapture() : void {
    $order = $this->createOrder($this->createGatewayPlugin());
    $builder = $this->prophesize(PaymentRequestBuilderInterface::class);
    $builder
      ->get(Argument::any(), Argument::any())
      ->shouldBeCalled()
      ->willReturn(
        (new PaymentStatusResponse())
          ->setStatus('ok')
          ->setTransactionId('123')
      );
    $sut = $this->mockPaymentGatewayPlugin($builder->reveal());
    $sut->onNotify($this->createRequest($order->id(), $sut));

    $payment = $this->loadPayment('123');
    static::assertEquals('completed', $payment->getState()->getId());
    static::assertEquals('ok', $payment->getRemoteState());
    static::assertEquals('22', $payment->getAmount()->getNumber());
  }

}
