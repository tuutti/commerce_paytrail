<?php

declare(strict_types = 1);

namespace Drupal\Tests\commerce_paytrail\Kernel;

use Drupal\commerce_payment\Entity\PaymentInterface;
use Drupal\commerce_paytrail\Exception\SecurityHashMismatchException;
use Drupal\commerce_paytrail\Plugin\Commerce\PaymentGateway\Paytrail;
use Drupal\commerce_paytrail\RequestBuilder\PaymentRequestBuilder;
use Paytrail\Payment\Model\Payment;
use Prophecy\Argument;
use Prophecy\Prophecy\ObjectProphecy;
use Symfony\Component\HttpFoundation\Request;

/**
 * Paytrail gateway plugin tests.
 *
 * @group commerce_paytrail
 * @coversDefaultClass \Drupal\commerce_paytrail\Plugin\Commerce\PaymentGateway\Paytrail
 */
class HandlePaymentTest extends RequestBuilderKernelTestBase {

  /**
   * Creates a Paytrail plugin using mocked request builder.
   *
   * @param \Drupal\commerce_paytrail\RequestBuilder\PaymentRequestBuilder $builder
   *   The mocked request builder.
   *
   * @return \Drupal\commerce_paytrail\Plugin\Commerce\PaymentGateway\Paytrail
   *   The payment gateway plugin.
   */
  protected function getSut(PaymentRequestBuilder $builder) : Paytrail {
    $this->container->set('commerce_paytrail.payment_request', $builder);
    $this->refreshServices();

    return $this->createGatewayPlugin('test')->getPlugin();
  }

  /**
   * Loads payment for given transaction id.
   *
   * @param string $transactionId
   *   The transaction id used to load the payment.
   *
   * @return \Drupal\commerce_payment\Entity\PaymentInterface|null
   *   The payment or null.
   */
  private function loadPayment(string $transactionId) :?  PaymentInterface {
    return $this->container
      ->get('entity_type.manager')
      ->getStorage('commerce_payment')
      ->loadByRemoteId($transactionId);
  }

  /**
   * Create mock request.
   *
   * @param int|string $orderId
   *   The order id.
   *
   * @return \Symfony\Component\HttpFoundation\Request
   *   The request.
   */
  private function createRequest(int|string $orderId) : Request {
    $request = Request::createFromGlobals();
    // Test with non-existent order.
    $request->query->set('checkout-reference', $orderId);
    $request->query->set('checkout-stamp', '123');

    return $request;
  }

  /**
   * Creates a mock builder for PaymentRequest builder.
   *
   * @return \Prophecy\Prophecy\ObjectProphecy
   *   The mock builder.
   */
  private function createRequestBuilderMock() : ObjectProphecy {
    $builder = $this->prophesize(PaymentRequestBuilder::class);
    $builder
      ->validateStamp(Argument::any(), Argument::any())
      ->shouldBeCalled()
      ->willReturn($builder->reveal());
    $builder->validateSignature(Argument::any(), Argument::any())
      ->shouldBeCalled()
      ->willReturn($builder->reveal());
    return $builder;
  }

  /**
   * Tests that payment fails when order is not found.
   */
  public function testOnNotifyOrderNotFound() : void {
    $builder = $this->prophesize(PaymentRequestBuilder::class);

    // Test with non-existent order.
    $response = $this->getSut($builder->reveal())
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

    $response = $this->getSut($builder->reveal())
      ->onNotify($this->createRequest($order->id()));
    static::assertEquals(403, $response->getStatusCode());
    static::assertEquals('Stamp validation failed.', $response->getContent());
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

    $response = $this->getSut($builder->reveal())
      ->onNotify($this->createRequest($order->id()));
    static::assertEquals(403, $response->getStatusCode());
    static::assertEquals('Signature does not match.', $response->getContent());
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

    $response = $this->getSut($builder->reveal())
      ->onNotify($this->createRequest($order->id()));
    static::assertEquals(403, $response->getStatusCode());
    static::assertStringStartsWith('Invalid status:', $response->getContent());
  }

  /**
   * Tests payment in 'authorize' state.
   *
   * This should make sure that we can create a payment in 'authorize'
   * state when the remote payment is still waiting for approval.
   */
  public function testHandlePaymentWithoutPaymentAuthorize() : void {
    $order = $this->createOrder();
    $builder = $this->createRequestBuilderMock();
    $builder
      ->get(Argument::any())
      ->shouldBeCalled()
      ->willReturn(
        (new Payment())
          ->setStatus(Payment::STATUS_PENDING)
          ->setTransactionId('123')
      );
    $this->getSut($builder->reveal())
      ->onNotify($this->createRequest($order->id()));

    $payment = $this->loadPayment('123');
    static::assertEquals('authorization', $payment->getState()->getId());
    static::assertEquals(Payment::STATUS_PENDING, $payment->getRemoteState());
    static::assertEquals('22', $payment->getAmount()->getNumber());
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
    $this->getSut($builder->reveal())
      ->onNotify($this->createRequest($order->id()));

    $payment = $this->loadPayment('123');
    static::assertEquals('completed', $payment->getState()->getId());
    static::assertEquals(Payment::STATUS_OK, $payment->getRemoteState());
    static::assertEquals('22', $payment->getAmount()->getNumber());
  }

}
