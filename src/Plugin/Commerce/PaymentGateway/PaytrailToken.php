<?php

declare(strict_types=1);

namespace Drupal\commerce_paytrail\Plugin\Commerce\PaymentGateway;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_payment\CreditCard;
use Drupal\commerce_payment\Entity\PaymentInterface;
use Drupal\commerce_payment\Entity\PaymentMethodInterface;
use Drupal\commerce_payment\Exception\PaymentGatewayException;
use Drupal\commerce_payment\PaymentMethodStorageInterface;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\SupportsStoredPaymentMethodsInterface;
use Drupal\commerce_paytrail\Exception\SecurityHashMismatchException;
use Drupal\commerce_paytrail\Plugin\Commerce\PaymentMethodType\PaytrailToken as PaytrailTokenMethod;
use Drupal\commerce_paytrail\RequestBuilder\TokenPaymentRequestBuilder;
use Paytrail\Payment\ApiException;
use Paytrail\Payment\Model\TokenizationRequestResponse;
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
 *     "amex", "dinersclub", "discover", "jcb", "maestro", "mastercard", "visa",
 *   },
 *   requires_billing_information = FALSE,
 * )
 */
final class PaytrailToken extends PaytrailBase implements SupportsStoredPaymentMethodsInterface {

  private TokenPaymentRequestBuilder $paymentRequestBuilder;

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
    $instance->paymentRequestBuilder = $container->get('commerce_paytrail.token_payment_request');
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

  public function onReturn(OrderInterface $order, Request $request) : void {
    try {
      $this->validateSignature($this, $request->query->all());

      if (!$token = $request->query->get('checkout-tokenization-id')) {
        throw new SecurityHashMismatchException('Missing required "checkout-tokenization-id".');
      }
      $payment_method_storage = $this->entityTypeManager->getStorage('commerce_payment_method');
      assert($payment_method_storage instanceof PaymentMethodStorageInterface);

      $payment_method = $payment_method_storage->createForCustomer(
        'paytrail_token',
        $this->parentEntity->id(),
        $order->getCustomerId(),
        $order->getBillingProfile()
      );
      $this->createPaymentMethod($payment_method, $token);
    }
    catch (SecurityHashMismatchException | ApiException | \InvalidArgumentException $e) {
      throw new PaymentGatewayException($e->getMessage(), previous: $e);
    }
  }

  public function createPaymentMethod(PaymentMethodInterface $paymentMethod, string $token) : void {
    $response = $this->paymentRequestBuilder->getCardForToken($this, $token);

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
      ->setRemoteId($response->getToken())
      ->save();
  }

  /**
   * {@inheritdoc}
   */
  public function createPayment(PaymentInterface $payment, $capture = TRUE) : void {
    $paymentMethod = $payment->getPaymentMethod();

    try {
      $order = $payment->getOrder();
      $response = $this->paymentRequestBuilder
        ->merchantInitiatedTransaction($this, $order, $paymentMethod->getRemoteId());
      $payment
        ->setRemoteId($response->getTransactionId())
        ->setAmount($order->getBalance())
        ->setAuthorizedTime($this->time->getCurrentTime())
        ->getState()
        ->applyTransitionById('authorize');

      if (!$payment->isCompleted() && $capture) {
        $payment->getState()
          ->applyTransitionById('capture');
      }
      $payment->save();
    }
    catch (ApiException $e) {
      throw new PaymentGatewayException($e->getMessage(), previous: $e);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function deletePaymentMethod(PaymentMethodInterface $payment_method) : void {
    $payment_method->delete();
  }

}
