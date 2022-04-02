<?php

declare(strict_types = 1);

namespace Drupal\Tests\commerce_paytrail\Kernel;

use Drupal\commerce_payment\Exception\PaymentGatewayException;
use Drupal\commerce_paytrail\Exception\SecurityHashMismatchException;
use Drupal\commerce_paytrail\RequestBuilder\PaymentRequestBuilder;
use Paytrail\Payment\Model\Payment;
use Prophecy\Argument;

/**
 * Paytrail gateway plugin tests.
 *
 * @group commerce_paytrail
 * @coversDefaultClass \Drupal\commerce_paytrail\Plugin\Commerce\PaymentGateway\Paytrail
 */
class HandlePaymentTest extends RequestBuilderKernelTestBase {

  /**
   * Tests that payment fails when order is not found.
   */
  public function testOnNotifyOrderNotFound() : void {
    $builder = $this->prophesize(PaymentRequestBuilder::class);

    // Test with non-existent order.
    $response = $this->getPaymentRequestBuilder($builder->reveal())
      ->onNotify($this->createRequest(55));
    static::assertEquals(403, $response->getStatusCode());
    static::assertEquals('Order not found.', $response->getContent());
  }

  /**
   * Tests that payment fails when stamp validation fails.
   */
  public function testHandlePaymentStampValidationFailed() : void {
    $builder = $this->prophesize(PaymentRequestBuilder::class);
    $builder
      ->validateStamp(Argument::any(), Argument::any())
      ->shouldBeCalled()
      ->willThrow(new SecurityHashMismatchException('Stamp validation failed.'));
    $builder->validateSignature(Argument::any(), Argument::any())
      ->shouldNotBeCalled();
    $order = $this->createOrder();

    $sut = $this->getPaymentRequestBuilder($builder->reveal());

    $response = $sut
      ->onNotify($this->createRequest($order->id()));
    static::assertEquals(403, $response->getStatusCode());
    static::assertEquals('Stamp validation failed.', $response->getContent());

    $this->expectException(PaymentGatewayException::class);
    $this->expectExceptionMessage('Stamp validation failed.');
    $sut->onReturn($order, $this->createRequest($order->id()));
  }

  /**
   * Tests that payment fails when signature validation fails.
   */
  public function testHandlePaymentSignatureValidationFailed() : void {
    $builder = $this->prophesize(PaymentRequestBuilder::class);
    $builder
      ->validateStamp(Argument::any(), Argument::any())
      ->shouldBeCalled()
      ->willReturn($builder->reveal());
    $builder->validateSignature(Argument::any(), Argument::any())
      ->shouldBeCalled()
      ->willThrow(new SecurityHashMismatchException('Signature does not match.'));
    $order = $this->createOrder();
    $sut = $this->getPaymentRequestBuilder($builder->reveal());

    $response = $sut
      ->onNotify($this->createRequest($order->id()));
    static::assertEquals(403, $response->getStatusCode());
    static::assertEquals('Signature does not match.', $response->getContent());

    $this->expectException(PaymentGatewayException::class);
    $this->expectExceptionMessage('Signature does not match.');
    $sut->onReturn($order, $this->createRequest($order->id()));
  }

  /**
   * Tests that payment fails when remote state is not correct.
   */
  public function testHandlePaymentInvalidResponseStatus() : void {
    $order = $this->createOrder();
    $builder = $this->createRequestBuilderMock();
    $builder
      ->get(Argument::any())
      ->shouldBeCalled()
      ->willReturn(
        (new Payment())
          ->setStatus(Payment::STATUS_FAIL)
      );

    $this->expectException(PaymentGatewayException::class);
    $this->expectExceptionMessage('Invalid status: ' . Payment::STATUS_FAIL);
    $sut = $this->getPaymentRequestBuilder($builder->reveal());
    $sut->onReturn($order, $this->createRequest($order->id()));
  }

  /**
   * Tests that payment can be fully captured.
   */
  public function testHandlePaymentCapture() : void {
    $order = $this->createOrder();
    $builder = $this->createRequestBuilderMock();
    $builder
      ->get(Argument::any())
      ->shouldBeCalled()
      ->willReturn(
        (new Payment())
          ->setStatus(Payment::STATUS_OK)
          ->setTransactionId('123')
      );
    $this->getPaymentRequestBuilder($builder->reveal())
      ->onReturn($order, $this->createRequest($order->id()));

    $payment = $this->loadPayment('123');
    static::assertEquals('completed', $payment->getState()->getId());
    static::assertEquals(Payment::STATUS_OK, $payment->getRemoteState());
    static::assertEquals('22', $payment->getAmount()->getNumber());
  }

}
