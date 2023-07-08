<?php

declare(strict_types = 1);

namespace Drupal\commerce_paytrail\Plugin\Commerce\PaymentGateway;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_payment\Exception\PaymentGatewayException;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\OffsitePaymentGatewayInterface;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\SupportsNotificationsInterface;
use Drupal\commerce_paytrail\Exception\SecurityHashMismatchException;
use Drupal\commerce_paytrail\ExceptionHelper;
use Drupal\commerce_paytrail\RequestBuilder\PaymentRequestBuilderInterface;
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
final class Paytrail extends PaytrailBase implements SupportsNotificationsInterface, OffsitePaymentGatewayInterface {

  /**
   * The payment request builder.
   *
   * @var \Drupal\commerce_paytrail\RequestBuilder\PaymentRequestBuilderInterface
   */
  private PaymentRequestBuilderInterface $paymentRequest;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) : static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->paymentRequest = $container->get('commerce_paytrail.payment_request');

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function onNotifySuccess(Request $request) : Response {
    $storage = $this->entityTypeManager->getStorage('commerce_order');

    try {
      $orderId = $request->query->get('checkout-reference');

      /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
      if (!$orderId || !$order = $storage->load($orderId)) {
        throw new PaymentGatewayException('Order not found.');
      }
      $this->handlePayment($order, $request, ['ok']);

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
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The commerce order.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   */
  public function onReturn(OrderInterface $order, Request $request) : void {
    try {
      $this->handlePayment($order, $request, [
        'ok',
        'pending',
        'delayed',
      ]);
    }
    catch (\Exception $e) {
      ExceptionHelper::handle($e);
    }
  }

  /**
   * Creates or captures a payment for given order.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The commerce order.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   * @param array $validPaymentStatuses
   *   The valid payment statuses.
   *
   * @throws \Drupal\commerce_paytrail\Exception\SecurityHashMismatchException
   */
  public function handlePayment(
    OrderInterface $order,
    Request $request,
    array $validPaymentStatuses,
  ) : void {
    $this->validateResponse($order, $request);

    $paymentResponse = $this->paymentRequest->get(
      $request->query->get('checkout-transaction-id'),
      $order
    );
    $this->assertResponseStatus($paymentResponse->getStatus(), $validPaymentStatuses);

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
    if (!$payment->isCompleted() && $paymentResponse->getStatus() === 'ok') {
      $payment->getState()
        ->applyTransitionById('capture');
    }
    $payment->save();
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
  protected function validateResponse(OrderInterface $order, Request $request) : void {
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
    $this->validateSignature($this->getSecret(), $request->query->all());
  }

}
