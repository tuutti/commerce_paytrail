<?php

declare(strict_types = 1);

namespace Drupal\Tests\commerce_paytrail\Kernel;

use Drupal\commerce_payment\Exception\PaymentGatewayException;
use Drupal\commerce_paytrail\Exception\SecurityHashMismatchException;
use Drupal\commerce_paytrail\RequestBuilder\PaymentRequestBuilderInterface;
use Paytrail\Payment\Model\Payment;
use Prophecy\Argument;
use Symfony\Component\HttpFoundation\Response;

/**
 * Paytrail gateway plugin tests.
 *
 * @group commerce_paytrail
 * @coversDefaultClass \Drupal\commerce_paytrail\Plugin\Commerce\PaymentGateway\Paytrail
 */
class HandlePaymentTest extends RequestBuilderKernelTestBase {

  /**
   * Tests that payment fails when signature validation fails.
   *
   * @covers ::onReturn
   * @covers ::onNotify
   * @covers ::validateResponse
   */
  public function testHandlePaymentSignatureValidationFailed() : void {
    $builder = $this->prophesize(PaymentRequestBuilderInterface::class);
    $builder->validateSignature(Argument::any(), Argument::any())
      ->shouldBeCalled()
      ->willThrow(new SecurityHashMismatchException('Signature does not match.'));
    $order = $this->createOrder();
    $sut = $this->getGatewayPluginForBuilder($builder->reveal());

    $response = $sut
      ->onNotify($this->createRequest($order->id()));
    static::assertEquals(403, $response->getStatusCode());
    static::assertEquals('Signature does not match.', $response->getContent());

    $this->expectException(PaymentGatewayException::class);
    $this->expectExceptionMessage('Signature does not match.');
    $sut->onReturn($order, $this->createRequest($order->id()));
  }

  /**
   * Tests that order id must match the checkout-reference query parameter.
   *
   * @covers ::onReturn
   * @covers ::onNotify
   */
  public function testInvalidOrderId() : void {
    $builder = $this->prophesize(PaymentRequestBuilderInterface::class);
    $order = $this->createOrder();
    $sut = $this->getGatewayPluginForBuilder($builder->reveal());

    $response = $sut
      ->onNotify($this->createRequest(55));
    static::assertEquals(403, $response->getStatusCode());
    static::assertEquals('Order not found.', $response->getContent());

    $this->expectException(PaymentGatewayException::class);
    $this->expectExceptionMessage('Order ID mismatch.');
    $sut->onReturn($order, $this->createRequest('non-existent-order'));
  }

  /**
   * Tests that payment fails when remote state is not correct.
   *
   * @covers ::onReturn
   * @covers ::onNotify
   */
  public function testHandlePaymentInvalidResponseStatus() : void {
    $order = $this->createOrder();
    $builder = $this->createRequestBuilderMock();
    $builder
      ->get(Argument::any(), Argument::any())
      ->shouldBeCalled()
      ->willReturn(
        (new Payment())
          ->setStatus(Payment::STATUS_FAIL)
      );
    $sut = $this->getGatewayPluginForBuilder($builder->reveal());

    $response = $sut->onNotify($this->createRequest($order->id()));
    static::assertEquals(Response::HTTP_FORBIDDEN, $response->getStatusCode());
    static::assertStringStartsWith('Invalid status: ' . Payment::STATUS_FAIL, $response->getContent());

    $this->expectException(PaymentGatewayException::class);
    $this->expectExceptionMessage('Invalid status: ' . Payment::STATUS_FAIL);
    $sut->onReturn($order, $this->createRequest($order->id()));
  }

  /**
   * Tests that payment can be fully captured.
   *
   * @covers ::onReturn
   */
  public function testHandlePaymentCapture() : void {
    $order = $this->createOrder();
    $builder = $this->createRequestBuilderMock();
    $builder
      ->get(Argument::any(), Argument::any())
      ->shouldBeCalled()
      ->willReturn(
        (new Payment())
          ->setStatus(Payment::STATUS_OK)
          ->setTransactionId('123')
      );
    $this->getGatewayPluginForBuilder($builder->reveal())
      ->onReturn($order, $this->createRequest($order->id()));

    $payment = $this->loadPayment('123');
    static::assertEquals('completed', $payment->getState()->getId());
    static::assertEquals(Payment::STATUS_OK, $payment->getRemoteState());
    static::assertEquals('22', $payment->getAmount()->getNumber());
  }

  /**
   * Tests that payment can be fully captured via onNotify().
   *
   * @covers ::onNotify
   */
  public function testHandleOnNotifyPaymentCapture() : void {
    $order = $this->createOrder();
    $builder = $this->createRequestBuilderMock();
    $builder
      ->get(Argument::any(), Argument::any())
      ->shouldBeCalled()
      ->willReturn(
        (new Payment())
          ->setStatus(Payment::STATUS_OK)
          ->setTransactionId('123')
      );
    $this->getGatewayPluginForBuilder($builder->reveal())
      ->onNotify($this->createRequest($order->id()));

    $payment = $this->loadPayment('123');
    static::assertEquals('completed', $payment->getState()->getId());
    static::assertEquals(Payment::STATUS_OK, $payment->getRemoteState());
    static::assertEquals('22', $payment->getAmount()->getNumber());
  }

}
