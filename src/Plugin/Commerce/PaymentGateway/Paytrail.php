<?php

declare(strict_types = 1);

namespace Drupal\commerce_paytrail\Plugin\Commerce\PaymentGateway;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_payment\Entity\PaymentInterface;
use Drupal\commerce_payment\Exception\PaymentGatewayException;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\SupportsNotificationsInterface;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\SupportsRefundsInterface;
use Drupal\commerce_paytrail\Exception\SecurityHashMismatchException;
use Drupal\commerce_paytrail\RequestBuilder\PaymentRequestBuilder;
use Drupal\commerce_paytrail\RequestBuilder\RefundRequestBuilder;
use Drupal\commerce_price\Price;
use Drupal\Core\Url;
use Paytrail\Payment\ApiException;
use Paytrail\Payment\Model\Payment;
use Paytrail\Payment\Model\RefundResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;
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
   * The payment request builder.
   *
   * @var \Drupal\commerce_paytrail\RequestBuilder\PaymentRequestBuilder
   */
  private PaymentRequestBuilder $paymentRequest;

  /**
   * The refund request builder.
   *
   * @var \Drupal\commerce_paytrail\RequestBuilder\RefundRequestBuilder
   */
  private RefundRequestBuilder $refundRequest;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) : static {
    /** @var \Drupal\commerce_paytrail\Plugin\Commerce\PaymentGateway\Paytrail $instance */
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);

    // Populate via setters to avoid overriding the parent constructor.
    $instance->paymentRequest = $container->get('commerce_paytrail.payment_request');
    $instance->refundRequest = $container->get('commerce_paytrail.refund_request');

    return $instance;
  }

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
      if (!$order = $storage->load($request->query->get('checkout-reference'))) {
        throw new PaymentGatewayException('Order not found.');
      }
      $this->handlePayment($order, $request);

      return new Response();
    }
    catch (PaymentGatewayException | SecurityHashMismatchException $e) {
      return new Response($e->getMessage(), Response::HTTP_FORBIDDEN);
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
    $this->paymentRequest
      // onNotify() uses {commerce_order} to load the order which is not a part
      // of signature hash calculation. Make sure stamp matches with the stamp
      // saved in order entity so a valid return URL cannot be re-used.
      ->validateStamp($order, $request->query->get('checkout-stamp'))
      ->validateSignature($this, $request->query->all());

    $paymentResponse = $this->paymentRequest->get($order);

    $this->assertResponseStatus($paymentResponse->getStatus(), [
      Payment::STATUS_OK,
      Payment::STATUS_PENDING,
      Payment::STATUS_DELAYED,
    ]);
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
    // Make sure we capture payment only once. Paytrail may attempt
    // to call notify callback before customer has returned from the
    // payment gateway and capture the payment.
    if (!$payment->isCompleted() && $paymentResponse->getStatus() === Payment::STATUS_OK) {
      $payment
        ->getState()
        ->applyTransitionById('capture');
    }
    $payment->save();

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
      $response = $this->refundRequest->refund($order, $amount);

      $this->assertResponseStatus($response->getStatus(), [
        RefundResponse::STATUS_OK,
        RefundResponse::STATUS_PENDING,
      ]);

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
