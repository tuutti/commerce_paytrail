<?php

declare(strict_types = 1);

namespace Drupal\Tests\commerce_paytrail\Kernel\Plugin\Commerce\PaymentGateway;

use Drupal\commerce_payment\Entity\Payment;
use Drupal\commerce_payment\Entity\PaymentInterface;
use Drupal\commerce_paytrail\RequestBuilder\RefundRequestBuilderInterface;
use Drupal\commerce_price\Price;
use Drupal\Tests\commerce_paytrail\Kernel\RequestBuilderKernelTestBase;
use Paytrail\SDK\Response\RefundResponse;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;

/**
 * Paytrail gateway plugin tests.
 *
 * @group commerce_paytrail
 * @coversDefaultClass \Drupal\commerce_paytrail\Plugin\Commerce\PaymentGateway\Paytrail
 */
class PaytrailRefundTest extends RequestBuilderKernelTestBase {

  use ProphecyTrait;

  /**
   * Creates a payment.
   *
   * @param bool $capture
   *   Whether to capture payment or not.
   *
   * @return \Drupal\commerce_payment\Entity\PaymentInterface
   *   The payment.
   */
  private function createPayment(bool $capture) : PaymentInterface {
    $order = $this->createOrder($this->createGatewayPlugin());
    $payment = Payment::create([
      'payment_gateway' => 'paytrail',
      'order_id' => $order->id(),
      'test' => FALSE,
    ]);
    $payment->setAmount(new Price('22', 'EUR'))
      ->setRemoteId('123')
      ->getState()
      ->applyTransitionById('authorize');

    if ($capture) {
      $payment->getState()
        ->applyTransitionById('capture');
    }
    $payment->save();

    return $payment;
  }

  /**
   * Make sure payments in incorrect state cannot be refunded.
   *
   * @covers \Drupal\commerce_paytrail\RequestBuilder\RefundRequestBuilder::refund
   * @covers \Drupal\commerce_paytrail\RequestBuilder\RefundRequestBuilder::createRefundRequest
   * @covers ::refundPayment
   * @covers ::assertRefundAmount
   * @covers ::assertPaymentState
   * @covers ::assertResponseStatus
   */
  public function testInvalidPaymentState() : void {
    $builder = $this->prophesize(RefundRequestBuilderInterface::class);
    $payment = $this->createPayment(FALSE);
    $sut = $this->mockPaymentGatewayPlugin(refundRequestBuilder: $builder->reveal());

    $this->expectExceptionMessage('The provided payment is in an invalid state ("authorization").');
    $sut->refundPayment($payment);
  }

  /**
   * Make sure refund fails if Paytrail refund request failed.
   *
   * @covers \Drupal\commerce_paytrail\RequestBuilder\RefundRequestBuilder::refund
   * @covers \Drupal\commerce_paytrail\RequestBuilder\RefundRequestBuilder::createRefundRequest
   * @covers ::refundPayment
   * @covers ::assertRefundAmount
   * @covers ::assertPaymentState
   * @covers ::assertResponseStatus
   */
  public function testInvalidResponseStatus() : void {
    $builder = $this->prophesize(RefundRequestBuilderInterface::class);
    $builder->refund(Argument::any(), Argument::any(), Argument::any())
      ->shouldBeCalled()
      ->willReturn(
        (new RefundResponse())
          ->setStatus('fail')
      );
    $payment = $this->createPayment(TRUE);
    $sut = $this->mockPaymentGatewayPlugin(refundRequestBuilder: $builder->reveal());

    $this->expectExceptionMessageMatches('/Invalid status\:/s');
    $sut->refundPayment($this->reloadEntity($payment));
  }

  /**
   * Tests partial refund.
   *
   * @covers \Drupal\commerce_paytrail\RequestBuilder\RefundRequestBuilder::refund
   * @covers \Drupal\commerce_paytrail\RequestBuilder\RefundRequestBuilder::createRefundRequest
   * @covers ::refundPayment
   * @covers ::assertRefundAmount
   * @covers ::assertPaymentState
   * @covers ::assertResponseStatus
   */
  public function testPartialRefund() : void {
    $builder = $this->prophesize(RefundRequestBuilderInterface::class);
    $builder->refund(Argument::any(), Argument::any(), Argument::any())
      ->shouldBeCalled()
      ->willReturn(
        (new RefundResponse())
          ->setStatus('ok')
      );
    $payment = $this->createPayment(TRUE);
    $sut = $this->mockPaymentGatewayPlugin(refundRequestBuilder: $builder->reveal());
    $sut->refundPayment($payment, new Price('10', 'EUR'));

    /** @var \Drupal\commerce_payment\Entity\PaymentInterface $payment */
    $payment = $this->reloadEntity($payment);
    static::assertEquals('partially_refunded', $payment->getState()->getId());
    static::assertEquals('12', $payment->getBalance()->getNumber());
    static::assertEquals('10', $payment->getRefundedAmount()->getNumber());
  }

  /**
   * Tests onNotify with refund events.
   *
   * @covers ::onNotify
   */
  public function testOnNotifyEvent() : void {
    $builder = $this->prophesize(RefundRequestBuilderInterface::class);
    $sut = $this->mockPaymentGatewayPlugin(refundRequestBuilder: $builder->reveal());

    foreach (['success', 'cancel'] as $event) {
      $request = $this->createRequest(1, $sut);
      $request->query->set('event', 'refund-' . $event);
      static::assertEquals(200, $sut->onNotify($request)->getStatusCode());
    }
  }

  /**
   * Tests full refund.
   *
   * @covers \Drupal\commerce_paytrail\RequestBuilder\RefundRequestBuilder::refund
   * @covers \Drupal\commerce_paytrail\RequestBuilder\RefundRequestBuilder::createRefundRequest
   * @covers ::refundPayment
   * @covers ::assertRefundAmount
   * @covers ::assertPaymentState
   * @covers ::assertResponseStatus
   */
  public function testFullRefund() : void {
    $builder = $this->prophesize(RefundRequestBuilderInterface::class);
    $builder->refund(Argument::any(), Argument::any(), Argument::any())
      ->shouldBeCalled()
      ->willReturn(
        (new RefundResponse())
          ->setStatus('ok')
      );
    $payment = $this->createPayment(TRUE);
    $sut = $this->mockPaymentGatewayPlugin(refundRequestBuilder: $builder->reveal());
    $sut->refundPayment($payment);

    /** @var \Drupal\commerce_payment\Entity\PaymentInterface $payment */
    $payment = $this->reloadEntity($payment);
    static::assertEquals('refunded', $payment->getState()->getId());
    static::assertEquals('0', $payment->getBalance()->getNumber());
    static::assertEquals('22', $payment->getRefundedAmount()->getNumber());
  }

}
