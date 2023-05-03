<?php

declare(strict_types = 1);

namespace Drupal\commerce_paytrail\Plugin\Commerce\PaymentGateway;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_payment\Entity\PaymentInterface;
use Drupal\commerce_payment\Exception\PaymentGatewayException;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\SupportsNotificationsInterface;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\SupportsRefundsInterface;
use Drupal\commerce_paytrail\Exception\SecurityHashMismatchException;
use Drupal\commerce_paytrail\RequestBuilder\PaymentRequestBuilderInterface;
use Drupal\commerce_paytrail\RequestBuilder\RefundRequestBuilderInterface;
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
   * @var \Drupal\commerce_paytrail\RequestBuilder\PaymentRequestBuilderInterface
   */
  private PaymentRequestBuilderInterface $paymentRequest;

  /**
   * The refund request builder.
   *
   * @var \Drupal\commerce_paytrail\RequestBuilder\RefundRequestBuilderInterface
   */
  private RefundRequestBuilderInterface $refundRequest;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) : static {
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
    $callback = match ($request->query->get('event')) {
      // Refunds can be asynchronous, meaning the refund can be in 'pending'
      // state and requires a valid success/cancel callback. Payments are
      // always marked as refunded regardless of its remote state.
      // Return a 200 response to make sure Paytrail doesn't keep
      // calling this for no reason.
      'refund-success', 'refund-cancel' => function (Request $request) : Response {
        return new Response();
      },
      default => function (Request $request) : Response {
        $storage = $this->entityTypeManager->getStorage('commerce_order');

        try {
          /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
          if (!$order = $storage->load($request->query->get('checkout-reference'))) {
            throw new PaymentGatewayException('Order not found.');
          }
          $this->validateResponse($order, $request);

          $paymentResponse = $this->paymentRequest->get(
            $request->query->get('checkout-transaction-id'),
            $order
          );
          $this->assertResponseStatus($paymentResponse->getStatus(), [
            Payment::STATUS_OK,
          ]);
          $this->createPayment($order, $paymentResponse);

          return new Response();
        }
        catch (PaymentGatewayException | SecurityHashMismatchException $e) {
          return new Response($e->getMessage(), Response::HTTP_FORBIDDEN);
        }
        return new Response(status: Response::HTTP_FORBIDDEN);
      }
    };

    return $callback($request);
  }

  /**
   * {@inheritdoc}
   */
  public function getNotifyUrl(string $eventName = NULL) : Url {
    $url = parent::getNotifyUrl();

    if ($eventName) {
      $query = $url->getOption('query');
      $query['event'] = $eventName;
      $url->setOption('query', $query);
    }
    return $url;
  }

  /**
   * Validate and store transaction for order.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The commerce order.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   */
  public function onReturn(OrderInterface $order, Request $request) : void {
    try {
      $this->validateResponse($order, $request);

      $paymentResponse = $this->paymentRequest->get(
        $request->query->get('checkout-transaction-id'),
        $order
      );
      $this->assertResponseStatus($paymentResponse->getStatus(), [
        Payment::STATUS_OK,
        Payment::STATUS_PENDING,
        Payment::STATUS_DELAYED,
      ]);
      $this->createPayment($order, $paymentResponse);
    }
    catch (SecurityHashMismatchException | ApiException $e) {
      throw new PaymentGatewayException($e->getMessage());
    }
  }

  /**
   * Validates the given order request.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   *
   * @throws \Drupal\commerce_paytrail\Exception\SecurityHashMismatchException
   */
  private function validateResponse(OrderInterface $order, Request $request) : void {
    [
      'checkout-reference' => $requestOrderId,
      'checkout-transaction-id' => $transactionId,
    ] = $request->query->all() + [
      'checkout-reference' => NULL,
      'checkout-transaction-id' => NULL,
    ];

    if (!$transactionId) {
      throw new SecurityHashMismatchException('Transaction ID not set.');
    }
    // onReturn() uses {commerce_order} to load the order, which is not a part
    // of the signature hash calculation. Make sure the order entity ID
    // matches the order id in 'checkout-reference' to make sure a valid return
    // URL cannot be reused.
    if (!$requestOrderId || $requestOrderId !== $order->id()) {
      throw new SecurityHashMismatchException('Order ID mismatch.');
    }
    $this->paymentRequest
      ->validateSignature($this, $request->query->all());
  }

  /**
   * Creates or captures a payment for given order.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The commerce order.
   * @param \Paytrail\Payment\Model\Payment $paymentResponse
   *   The payment response.
   */
  public function createPayment(
    OrderInterface $order,
    Payment $paymentResponse
  ) : void {
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
        ->setAuthorizedTime($this->time->getCurrentTime())
        ->setRemoteState($paymentResponse->getStatus())
        ->getState()
        ->applyTransitionById('authorize');
    }
    if (!$payment->isCompleted() && $paymentResponse->getStatus() === Payment::STATUS_OK) {
      $payment->getState()
        ->applyTransitionById('capture');
    }
    $payment->save();
  }

  /**
   * {@inheritdoc}
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
      $response = $this->refundRequest->refund($payment->getRemoteId(), $order, $amount);

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
      $message = $e->getMessage();

      if ($e instanceof ApiException) {
        $message = json_decode($e->getResponseBody())->message ?? $e->getMessage();
      }
      throw new PaymentGatewayException($message, $e->getCode(), $e);
    }
  }

}
