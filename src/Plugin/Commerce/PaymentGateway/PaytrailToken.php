<?php

declare(strict_types=1);

namespace Drupal\commerce_paytrail\Plugin\Commerce\PaymentGateway;

use Drupal\commerce_checkout\Entity\CheckoutFlowInterface;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_payment\CreditCard;
use Drupal\commerce_payment\Entity\PaymentInterface;
use Drupal\commerce_payment\Entity\PaymentMethodInterface;
use Drupal\commerce_payment\Exception\PaymentGatewayException;
use Drupal\commerce_payment\PaymentMethodStorageInterface;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\OffsitePaymentGatewayInterface;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\SupportsAuthorizationsInterface;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\SupportsStoredPaymentMethodsInterface;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\SupportsVoidsInterface;
use Drupal\commerce_paytrail\Exception\SecurityHashMismatchException;
use Drupal\commerce_paytrail\ExceptionHelper;
use Drupal\commerce_paytrail\RequestBuilder\PaymentRequestBuilderInterface;
use Drupal\commerce_paytrail\RequestBuilder\TokenRequestBuilderInterface;
use Drupal\commerce_price\Price;
use GuzzleHttp\Exception\RequestException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Provides the Paytrail payment gateway.
 *
 * @CommercePaymentGateway(
 *   id = "paytrail_token",
 *   label = "Paytrail (Credit card)",
 *   display_label = "Paytrail (Credit card)",
 *   forms = {
 *     "offsite-payment" = "Drupal\commerce_paytrail\PluginForm\OffsiteRedirect\PaytrailTokenForm",
 *   },
 *   payment_method_types = {"paytrail_token"},
 *   credit_card_types = {
 *     "amex", "mastercard", "visa",
 *   },
 *   requires_billing_information = FALSE,
 * )
 */
final class PaytrailToken extends PaytrailBase implements OffsitePaymentGatewayInterface, SupportsStoredPaymentMethodsInterface, SupportsVoidsInterface, SupportsAuthorizationsInterface {

  /**
   * The token payment request builder.
   *
   * @var \Drupal\commerce_paytrail\RequestBuilder\TokenRequestBuilderInterface
   */
  private TokenRequestBuilderInterface $paymentTokenRequest;

  /**
   * The payment request builder.
   *
   * @var \Drupal\commerce_paytrail\RequestBuilder\PaymentRequestBuilderInterface
   */
  private PaymentRequestBuilderInterface $paymentRequestBuilder;

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition
  ): static {
    $instance = parent::create(
      $container,
      $configuration,
      $plugin_id,
      $plugin_definition
    );
    $instance->paymentTokenRequest = $container->get('commerce_paytrail.token_payment_request');
    $instance->paymentRequestBuilder = $container->get('commerce_paytrail.payment_request');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() : array {
    return [
      'payment_method_types' => ['paytrail_token'],
      'capture' => TRUE,
    ] + parent::defaultConfiguration();
  }

  /**
   * Whether to capture the payment automatically on return.
   *
   * @return bool
   *   TRUE if payment should be captured on return.
   */
  public function autoCaptureEnabled(OrderInterface $order) : bool {
    $captureSetting = (bool) $this->configuration['capture'];

    if ($captureSetting === FALSE) {
      return FALSE;
    }

    // Attempt to mirror 'Transaction mode' setting in checkout flow.
    if (!$order->hasField('checkout_flow')) {
      return TRUE;
    }
    $checkoutFlow = $order->get('checkout_flow')?->entity;

    if (!$checkoutFlow instanceof CheckoutFlowInterface) {
      return TRUE;
    }

    $configuration = $checkoutFlow->getPlugin()
      ?->getPane('payment_process')
      ?->getConfiguration();

    if (isset($configuration['capture'])) {
      return (bool) $configuration['capture'];
    }
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  protected function onNotifySuccess(Request $request): Response {
    $storage = $this->entityTypeManager->getStorage('commerce_order');

    try {
      $orderId = $request->query->get('commerce_order');

      /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
      if (!$orderId || !$order = $storage->load($orderId)) {
        throw new PaymentGatewayException('Order not found.');
      }
      $this->validateResponse($order, $request);
      $this->handlePayment($order, $request->query->get('checkout-tokenization-id'));
    }
    catch (SecurityHashMismatchException | PaymentGatewayException $e) {
      return new Response($e->getMessage(), Response::HTTP_FORBIDDEN);
    }
    return new Response();
  }

  /**
   * {@inheritdoc}
   */
  public function onReturn(OrderInterface $order, Request $request) : void {
    try {
      $this->validateResponse($order, $request);
      $this->handlePayment($order, $request->query->get('checkout-tokenization-id'));
    }
    catch (SecurityHashMismatchException | RequestException $e) {
      ExceptionHelper::handle($e);
    }
  }

  /**
   * Validates the response.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   *
   * @throws \Drupal\commerce_paytrail\Exception\SecurityHashMismatchException
   */
  protected function validateResponse(OrderInterface $order, Request $request) : void {
    if (!$request->query->get('checkout-tokenization-id')) {
      throw new SecurityHashMismatchException('Tokenization ID not set.');
    }
    $stamp = (string) $request->query->get('commerce_paytrail_stamp');
    $orderStamp = (string) $order->getData(TokenRequestBuilderInterface::TOKEN_STAMP_KEY);

    if (!$stamp || !$orderStamp || $stamp !== $orderStamp) {
      throw new SecurityHashMismatchException('Order stamp does not match.');
    }
    $this->validateSignature($this->getSecret(), $request->query->all());
  }

  /**
   * Handles the payment for new cards.
   *
   * This is only called when a customer adds a new card.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order.
   * @param string $token
   *   The tokenization token.
   */
  protected function handlePayment(OrderInterface $order, string $token) : void {
    $paymentMethodStorage = $this->entityTypeManager->getStorage('commerce_payment_method');
    assert($paymentMethodStorage instanceof PaymentMethodStorageInterface);

    $paymentMethod = $paymentMethodStorage->createForCustomer(
      'paytrail_token',
      $this->parentEntity->id(),
      $order->getCustomerId(),
      $order->getBillingProfile()
    );

    $paymentMethod = $this->createPaymentMethod(
      $paymentMethod,
      $token,
    );
    /** @var \Drupal\commerce_payment\PaymentStorageInterface $paymentStorage */
    $paymentStorage = $this->entityTypeManager->getStorage('commerce_payment');
    /** @var \Drupal\commerce_payment\Entity\PaymentInterface $payment */
    $payment = $paymentStorage->create([
      'payment_gateway' => $this->parentEntity->id(),
      'order_id' => $order->id(),
      'test' => !$this->isLive(),
      'payment_method' => $paymentMethod,
    ]);
    $this->createPayment($payment, $this->autoCaptureEnabled($order));
    $paymentMethod->save();
  }

  /**
   * Populates the payment method with external credit card details.
   *
   * @param \Drupal\commerce_payment\Entity\PaymentMethodInterface $paymentMethod
   *   The payment method.
   * @param string $token
   *   The token.
   *
   * @return \Drupal\commerce_payment\Entity\PaymentMethodInterface
   *   The created payment method.
   */
  public function createPaymentMethod(PaymentMethodInterface $paymentMethod, string $token) : PaymentMethodInterface {
    $response = $this->paymentTokenRequest->getCardForToken($this, $token);
    $card = $response->getCard();

    $paymentMethod->card_type = strtolower($card->getType());
    $paymentMethod->card_number = $card->getPartialPan();
    $paymentMethod->card_exp_month = $card->getExpireMonth();
    $paymentMethod->card_exp_year = $card->getExpireYear();

    $expires = CreditCard::calculateExpirationTimestamp(
      $paymentMethod->card_exp_month->value,
      $paymentMethod->card_exp_year->value,
    );
    $paymentMethod->setExpiresTime($expires)
      ->setRemoteId($response->getToken());

    return $paymentMethod;
  }

  /**
   * {@inheritdoc}
   */
  public function createPayment(PaymentInterface $payment, $capture = TRUE) : void {
    $paymentMethod = $payment->getPaymentMethod();
    $this->assertPaymentState($payment, ['new']);
    $this->assertPaymentMethod($paymentMethod);

    try {
      $order = $payment->getOrder();
      $response = $this->paymentTokenRequest
        ->tokenMitAuthorize($order, $paymentMethod->getRemoteId());

      $payment
        ->setRemoteId($response->getTransactionId())
        ->setAmount($order->getBalance())
        ->setAuthorizedTime($this->time->getCurrentTime())
        ->getState()
        ->applyTransitionById('authorize');
      $payment->save();

      if ($capture) {
        $this->capturePayment($payment);
      }
    }
    catch (RequestException $e) {
      ExceptionHelper::handle($e);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function deletePaymentMethod(PaymentMethodInterface $payment_method) : void {
    $payment_method->delete();
  }

  /**
   * {@inheritdoc}
   */
  public function voidPayment(PaymentInterface $payment) : void {
    $this->assertPaymentState($payment, ['authorization']);

    try {
      $this->paymentTokenRequest
        ->tokenRevert($payment);
      $payment->setState('authorization_voided');
      $payment->save();
    }
    catch (RequestException $e) {
      ExceptionHelper::handle($e);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function capturePayment(
    PaymentInterface $payment,
    Price $amount = NULL
  ) : void {
    $this->assertPaymentState($payment, ['authorization']);
    $amount = $amount ?: $payment->getAmount();

    try {
      $response = $this->paymentTokenRequest
        ->tokenCommit($payment, $amount);

      $paymentResponse = $this->paymentRequestBuilder
        ->get($response->getTransactionId(), $payment->getOrder());

      $payment->setRemoteState($paymentResponse->getStatus())
        ->setAmount($amount);

      if (!$payment->isCompleted() && $paymentResponse->getStatus() === 'ok') {
        $payment
          ->getState()
          ->applyTransitionById('capture');
      }
      $payment->save();
    }
    catch (RequestException $e) {
      ExceptionHelper::handle($e);
    }
  }

}
