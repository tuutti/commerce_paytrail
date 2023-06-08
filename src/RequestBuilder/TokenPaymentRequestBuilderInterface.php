<?php

declare(strict_types=1);

namespace Drupal\commerce_paytrail\RequestBuilder;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_payment\Entity\PaymentInterface;
use Drupal\commerce_paytrail\Plugin\Commerce\PaymentGateway\PaytrailToken;
use Drupal\commerce_price\Price;
use Paytrail\Payment\Model\TokenizationRequestResponse;
use Paytrail\Payment\Model\TokenMITPaymentResponse;
use Paytrail\Payment\Model\TokenPaymentRequest;

/**
 * The token payment request builder.
 */
interface TokenPaymentRequestBuilderInterface {

  public const TOKEN_ADD_CARD_FORM_EVENT = 'token_payment_add_card_form';
  public const TOKEN_GET_CARD_EVENT = 'token_payment_get_card';
  public const TOKEN_GET_CARD_RESPONSE_EVENT = 'token_payment_get_card_response';
  public const TOKEN_REVERT_RESPONSE_EVENT = 'token_payment_token_revert_response';
  public const TOKEN_COMMIT_EVENT = 'token_payment_commit';
  public const TOKEN_COMMIT_RESPONSE_EVENT = 'token_payment_commit_response';
  public const TOKEN_MIT_AUTHORIZE_EVENT = 'token_payment_mit_authorize';
  public const TOKEN_MIT_AUTHORIZE_RESPONSE_EVENT = 'token_payment_mit_authorize_response';
  public const TOKEN_MIT_CHARGE_EVENT = 'token_payment_mit_charge';
  public const TOKEN_MIT_CHARGE_RESPONSE_EVENT = 'token_payment_mit_charge_response';

  /**
   * Creates a new add card form for given order.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order to create form for.
   *
   * @return array
   *   The add card form.
   */
  public function createAddCardFormForOrder(OrderInterface $order) : array;

  /**
   * Gets the card for given token.
   *
   * @param \Drupal\commerce_paytrail\Plugin\Commerce\PaymentGateway\PaytrailToken $plugin
   *   The payment gateway plugin.
   * @param string $token
   *   The token.
   *
   * @return \Paytrail\Payment\Model\TokenizationRequestResponse
   *   The tokenization response.
   */
  public function getCardForToken(PaytrailToken $plugin, string $token): TokenizationRequestResponse;

  /**
   * Reverts the given token.
   *
   * @param \Drupal\commerce_payment\Entity\PaymentInterface $payment
   *   The payment to revert.
   *
   * @return \Paytrail\Payment\Model\TokenMITPaymentResponse
   *   The payment response.
   */
  public function tokenRevert(PaymentInterface $payment): TokenMITPaymentResponse;

  /**
   * Commits the authorization hold.
   *
   * @param \Drupal\commerce_payment\Entity\PaymentInterface $payment
   *   The payment associated with commit.
   * @param \Drupal\commerce_price\Price $amount
   *   The price to charge from card.
   *
   * @return \Paytrail\Payment\Model\TokenMITPaymentResponse
   *   The payment response.
   */
  public function tokenCommit(PaymentInterface $payment, Price $amount): TokenMITPaymentResponse;

  /**
   * Performs a MIT authorization for given order and token.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order.
   * @param string $token
   *   The token.
   *
   * @return \Paytrail\Payment\Model\TokenMITPaymentResponse
   *   The payment response.
   */
  public function tokenMitAuthorize(OrderInterface $order, string $token): TokenMITPaymentResponse;

  /**
   * Performs a MIT charge for given token and order.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order.
   * @param string $token
   *   The token.
   *
   * @return \Paytrail\Payment\Model\TokenMITPaymentResponse
   *   The payment response.
   */
  public function tokenMitCharge(OrderInterface $order, string $token): TokenMITPaymentResponse;

  /**
   * Constructs a new token payment request object.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order.
   * @param string $token
   *   The token.
   * @param string $event
   *   The event.
   *
   * @return \Paytrail\Payment\Model\TokenPaymentRequest
   *   The token payment request.
   */
  public function createTokenPaymentRequest(OrderInterface $order, string $token, string $event) : TokenPaymentRequest;

}
