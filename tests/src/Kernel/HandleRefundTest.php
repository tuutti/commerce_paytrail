<?php

declare(strict_types = 1);

namespace Drupal\Tests\commerce_paytrail\Kernel;

use Drupal\commerce_payment\Entity\Payment;
use Drupal\commerce_payment\Entity\PaymentInterface;
use Drupal\commerce_paytrail\RequestBuilder\RefundRequestBuilder;
use Drupal\commerce_price\Price;
use Paytrail\Payment\Model\RefundResponse;
use Prophecy\Argument;

/**
 * Paytrail gateway plugin tests.
 *
 * @group commerce_paytrail
 * @coversDefaultClass \Drupal\commerce_paytrail\Plugin\Commerce\PaymentGateway\Paytrail
 */
class HandleRefundTest extends RequestBuilderKernelTestBase {

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
    $order = $this->createOrder();
    $payment = Payment::create([
      'payment_gateway' => 'paytrail',
      'order_id' => $order->id(),
      'test' => FALSE,
    ]);
    $payment->setAmount(new Price('22', 'EUR'))
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
   */
  public function testInvalidPaymentState() : void {
    $builder = $this->prophesize(RefundRequestBuilder::class);
    $payment = $this->createPayment(FALSE);
    $sut = $this->getPaymentRequestBuilder($builder->reveal());

    $this->expectErrorMessage('The provided payment is in an invalid state ("authorization").');
    $sut->refundPayment($payment);
  }

  /**
   * Make sure refund fails if Paytrail refund request failed.
   */
  public function testInvalidResponseStatus() : void {
    $builder = $this->prophesize(RefundRequestBuilder::class);
    $builder->refund(Argument::any(), Argument::any())
      ->shouldBeCalled()
      ->willReturn(
        (new RefundResponse())
          ->setStatus(RefundResponse::STATUS_FAIL)
      );
    $payment = $this->createPayment(TRUE);
    $sut = $this->getPaymentRequestBuilder($builder->reveal());

    $this->expectErrorMessageMatches('/Invalid status\:/s');
    $sut->refundPayment($this->reloadEntity($payment));
  }

  /**
   * Tests partial refund.
   */
  public function testPartialRefund() : void {
    $builder = $this->prophesize(RefundRequestBuilder::class);
    $builder->refund(Argument::any(), Argument::any())
      ->shouldBeCalled()
      ->willReturn(
        (new RefundResponse())
          ->setStatus(RefundResponse::STATUS_OK)
      );
    $payment = $this->createPayment(TRUE);
    $sut = $this->getPaymentRequestBuilder($builder->reveal());
    $sut->refundPayment($payment, new Price('10', 'EUR'));

    /** @var \Drupal\commerce_payment\Entity\PaymentInterface $payment */
    $payment = $this->reloadEntity($payment);
    static::assertEquals('partially_refunded', $payment->getState()->getId());
    static::assertEquals('12', $payment->getBalance()->getNumber());
    static::assertEquals('10', $payment->getRefundedAmount()->getNumber());
  }

  /**
   * Tests full refund.
   */
  public function testFullRefund() : void {
    $builder = $this->prophesize(RefundRequestBuilder::class);
    $builder->refund(Argument::any(), Argument::any())
      ->shouldBeCalled()
      ->willReturn(
        (new RefundResponse())
          ->setStatus(RefundResponse::STATUS_OK)
      );
    $payment = $this->createPayment(TRUE);
    $sut = $this->getPaymentRequestBuilder($builder->reveal());
    $sut->refundPayment($payment);

    /** @var \Drupal\commerce_payment\Entity\PaymentInterface $payment */
    $payment = $this->reloadEntity($payment);
    static::assertEquals('refunded', $payment->getState()->getId());
    static::assertEquals('0', $payment->getBalance()->getNumber());
    static::assertEquals('22', $payment->getRefundedAmount()->getNumber());
  }

}
