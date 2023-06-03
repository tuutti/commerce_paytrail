<?php

declare(strict_types=1);

namespace Drupal\commerce_paytrail\Plugin\Commerce\PaymentGateway;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_payment\CreditCard;
use Drupal\commerce_payment\Entity\PaymentInterface;
use Drupal\commerce_payment\Entity\PaymentMethodInterface;
use Drupal\commerce_payment\PaymentMethodStorageInterface;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\OffsitePaymentGatewayInterface;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\SupportsAuthorizationsInterface;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\SupportsStoredPaymentMethodsInterface;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\SupportsVoidsInterface;
use Drupal\commerce_paytrail\Exception\SecurityHashMismatchException;
use Drupal\commerce_paytrail\ExceptionHelper;
use Drupal\commerce_paytrail\RequestBuilder\PaymentRequestBuilder;
use Drupal\commerce_paytrail\RequestBuilder\TokenPaymentRequestBuilder;
use Drupal\commerce_price\Price;
use Paytrail\Payment\ApiException;
use Paytrail\Payment\Model\Payment;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Provides the Paytrail payment gateway.
 *
 * @CommercePaymentGateway(
 *   id = "paytrail_token",
 *   label = "Paytrail (Add card)",
 *   display_label = "Paytrail (Add card)",
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
   * @var \Drupal\commerce_paytrail\RequestBuilder\TokenPaymentRequestBuilder
   */
  private TokenPaymentRequestBuilder $paymentTokenRequest;

  /**
   * The payment request builder.
   *
   * @var \Drupal\commerce_paytrail\RequestBuilder\PaymentRequestBuilder
   */
  private PaymentRequestBuilder $paymentRequestBuilder;

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
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function onReturn(OrderInterface $order, Request $request) : void {
    try {
      $this->validateSignature($this, $request->query->all());

      if (!$token = $request->query->get('checkout-tokenization-id')) {
        throw new SecurityHashMismatchException('Missing required "checkout-tokenization-id".');
      }
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
      // @todo Figure out if we should capture the payment here.
      $this->createPayment($payment, FALSE);
      $paymentMethod->save();
    }
    catch (SecurityHashMismatchException | ApiException | \InvalidArgumentException $e) {
      ExceptionHelper::handle($e);
    }
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
   *
   * @throws \JsonException
   */
  public function createPaymentMethod(PaymentMethodInterface $paymentMethod, string $token) : PaymentMethodInterface {
    $response = $this->paymentTokenRequest->getCardForToken($this, $token);

    if (!$card = $response->getCard()) {
      throw new \InvalidArgumentException('Failed to fetch card details.');
    }
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

      if ($capture) {
        $this->capturePayment($payment);

        $paymentResponse = $this->paymentRequestBuilder
          ->get($response->getTransactionId(), $order);

        if (!$payment->isCompleted() && $paymentResponse->getStatus() === Payment::STATUS_OK) {
          $payment->getState()
            ->applyTransitionById('capture');
        }
      }
      $payment->save();
    }
    catch (ApiException $e) {
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
    }
    catch (ApiException $e) {
      ExceptionHelper::handle($e);
    }
    $payment->setState('authorization_voided');
    $payment->save();
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

      if (!$payment->isCompleted() && $paymentResponse->getStatus() === Payment::STATUS_OK) {
        $payment->getState()
          ->applyTransitionById('capture');
      }
      $payment->save();
    }
    catch (ApiException $e) {
      ExceptionHelper::handle($e);
    }
  }

}
