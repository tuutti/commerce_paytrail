<?php

declare(strict_types = 1);

namespace Drupal\commerce_paytrail\Plugin\Commerce\PaymentGateway;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_payment\Entity\PaymentInterface;
use Drupal\commerce_payment\Exception\PaymentGatewayException;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\SupportsNotificationsInterface;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\SupportsRefundsInterface;
use Drupal\commerce_paytrail\Exception\SecurityHashMismatchException;
use Drupal\commerce_price\Price;
use Drupal\Core\Url;
use Paytrail\Payment\ApiException;
use Paytrail\Payment\Model\Payment;
use Paytrail\Payment\Model\RefundResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Provides the Paytrail payment gateway.
 *
 * @CommercePaymentGateway(
 *   id = "paytrail",
 *   label = "Paytrail",
 *   display_label = "Paytrail",
 *   forms = {
 *     "offsite-payment" = "Drupal\commerce_paytrail\PluginForm\OffsiteRedirect\PaytrailOffsiteForm",
 *   },
 *   payment_method_types = {"paytrail"},
 *   requires_billing_information = FALSE,
 * )
 */
final class Paytrail extends PaytrailBase implements SupportsNotificationsInterface, SupportsRefundsInterface {
  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() : array {
    return [
      'payment_method_types' => ['paytrail'],
    ] + parent::defaultConfiguration();
  }

  /**
   * Gets the return URL for given order.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order.
   *
   * @return \Drupal\Core\Url
   *   The return url.
   */
  public function getReturnUrl(OrderInterface $order) : Url {
    return $this->buildReturnUrl($order, 'commerce_payment.checkout.return');
  }

  /**
   * Gets the cancel URL for given order.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order.
   *
   * @return \Drupal\Core\Url
   *   The cancel url.
   */
  public function getCancelUrl(OrderInterface $order) : Url {
    return $this->buildReturnUrl($order, 'commerce_payment.checkout.cancel');
  }

  /**
   * Notify callback.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The response.
   */
  public function onNotify(Request $request) : Response {
    $storage = $this->entityTypeManager->getStorage('commerce_order');

    try {
      /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
      if (!$order = $storage->load($request->get('checkout-reference'))) {
        throw new PaymentGatewayException('Order not found.');
      }
      $this->handlePayment($order, $request);

      return new Response();
    }
    catch (PaymentGatewayException | SecurityHashMismatchException $e) {
    }
    return new Response(status: Response::HTTP_FORBIDDEN);
  }

  /**
   * Validate and store transaction for order.
   *
   * Payment will be initially stored as 'authorized' until
   * paytrail calls the notify IPN.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The commerce order.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   */
  public function onReturn(OrderInterface $order, Request $request) : void {
    try {
      $this->handlePayment($order, $request);
    }
    catch (SecurityHashMismatchException | ApiException $e) {
      throw new PaymentGatewayException($e->getMessage());
    }
  }

  /**
   * Creates or captures a payment for given order.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The commerce order.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   *
   * @return \Drupal\commerce_payment\Entity\PaymentInterface
   *   The payment.
   *
   * @throws \Drupal\commerce_paytrail\Exception\SecurityHashMismatchException
   * @throws \Paytrail\Payment\ApiException
   */
  protected function handlePayment(OrderInterface $order, Request $request) : PaymentInterface {
    $this->paymentRequestBuilder
      ->validateSignature($this, $request->query->all())
      // onNotify() uses {commerce_order} to load the order which is not a part
      // of signature hash calculation. Make sure stamp matches with the stamp
      // saved in order entity so a valid return URL cannot be re-used.
      ->validateStamp($order, $request->query->get('checkout-stamp'));

    $paymentResponse = $this->paymentRequestBuilder->get($order);

    $allowedStatuses = [
      Payment::STATUS_OK,
      Payment::STATUS_PENDING,
      Payment::STATUS_DELAYED,
    ];

    if (!in_array($paymentResponse->getStatus(), $allowedStatuses)) {
      throw new PaymentGatewayException('Payment not marked as paid.');
    }
    /** @var \Drupal\commerce_payment\PaymentStorageInterface $storage */
    $storage = $this->entityTypeManager->getStorage('commerce_payment');

    // Create payment if one does not exist yet.
    if (!$payment = $storage->loadByRemoteId($paymentResponse->getTransactionId())) {
      /** @var \Drupal\commerce_payment\Entity\PaymentInterface $payment */
      $payment = $storage->create([
        'payment_gateway' => $this->parentEntity->id(),
        'order_id' => $order->id(),
        'test' => !$this->isLive(),
      ]);
      $payment->setAmount($order->getBalance())
        ->setRemoteId($paymentResponse->getTransactionId())
        ->setRemoteState($paymentResponse->getStatus())
        ->getState()
        ->applyTransitionById('authorize');
    }
    // Make sure to only capture transition once in case Paytrail calls the
    // callback URL and completes the order before customer has returned from
    // the payment gateway.
    if (!$payment->isCompleted() && $paymentResponse->getStatus() === Payment::STATUS_OK) {
      $payment->getState()
        ->applyTransitionById('capture');
      $payment->save();
    }

    return $payment;
  }

  /**
   * {@inheritdoc}
   *
   * @todo Refunds can be asynchronous in the future.
   * @see https://docs.paytrail.com/#/?id=refund
   */
  public function refundPayment(PaymentInterface $payment, Price $amount = NULL) : void {
    $this->assertPaymentState($payment, ['completed', 'partially_refunded']);
    // If not specified, refund the entire amount.
    $amount = $amount ?: $payment->getAmount();
    // Validate the requested amount.
    $this->assertRefundAmount($payment, $amount);
    $order = $payment->getOrder();

    $oldRefundedAmount = $payment->getRefundedAmount();
    $newRefundedAmount = $oldRefundedAmount->add($amount);

    try {
      $response = $this->paymentRequestBuilder->refund($order, $amount);

      $allowedStatuses = [
        RefundResponse::STATUS_OK,
        RefundResponse::STATUS_PENDING,
      ];

      if (!in_array($response->getStatus(), $allowedStatuses)) {
        throw new PaymentGatewayException('Refund failed.');
      }
      $newRefundedAmount->lessThan($payment->getAmount()) ?
        $payment->setState('partially_refunded') :
        $payment->setState('refunded');

      $payment->setRefundedAmount($newRefundedAmount)
        ->save();
    }
    catch (\Exception $e) {
      throw new PaymentGatewayException($e->getMessage(), $e->getCode(), $e);
    }
  }

}
