<?php

declare(strict_types = 1);

namespace Drupal\commerce_paytrail\RequestBuilder;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_payment\Entity\PaymentInterface;
use Drupal\commerce_paytrail\Event\ModelEvent;
use Drupal\commerce_paytrail\Plugin\Commerce\PaymentGateway\PaytrailToken;
use Drupal\commerce_price\Price;
use Paytrail\Payment\Api\TokenPaymentsApi;
use Paytrail\Payment\ApiException;
use Paytrail\Payment\Model\AddCardFormRequest;
use Paytrail\Payment\Model\Error;
use Paytrail\Payment\Model\GetTokenRequest;
use Paytrail\Payment\Model\TokenizationRequestResponse;
use Paytrail\Payment\Model\TokenMITPaymentResponse;
use Paytrail\Payment\Model\TokenPaymentRequest;
use Paytrail\Payment\ObjectSerializer;

/**
 * The token payment request builder.
 *
 * @internal
 */
final class TokenPaymentRequestBuilder extends PaymentRequestBase implements TokenPaymentRequestBuilderInterface {

  /**
   * {@inheritdoc}
   */
  public function createAddCardFormForOrder(OrderInterface $order) : array {
    $plugin = $this->getPaymentPlugin($order);

    $configuration = $plugin
      ->getClientConfiguration();
    $headers = $this->createHeaders('POST', $configuration);

    $request = (new AddCardFormRequest())
      ->setLanguage($plugin->getLanguage())
      ->setCheckoutAccount($headers->account)
      ->setCheckoutAlgorithm($headers->hashAlgorithm)
      ->setCheckoutTimestamp($headers->timestamp)
      ->setCheckoutNonce($headers->nonce)
      ->setCheckoutMethod($headers->method)
      ->setCheckoutRedirectSuccessUrl($plugin->getReturnUrl($order)->toString())
      ->setCheckoutRedirectCancelUrl($plugin->getCancelUrl($order)->toString())
      ->setCheckoutCallbackSuccessUrl($plugin->getNotifyUrl()->toString())
      ->setCheckoutCallbackCancelUrl($plugin->getNotifyUrl()->toString());

    $this->eventDispatcher
      ->dispatch(new ModelEvent($request, $headers, $order));

    $signature = $this->signature(
      $configuration->getApiKey('secret'),
      (array) ObjectSerializer::sanitizeForSerialization($request)
    );
    $request->setSignature($signature);

    $tokenApi = new TokenPaymentsApi($this->client, $configuration);

    return [
      'uri' => (string) $tokenApi->addCardFormRequest($request)->getUri(),
      'data' => (array) ObjectSerializer::sanitizeForSerialization($request),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getCardForToken(PaytrailToken $plugin, string $token) : TokenizationRequestResponse {
    $configuration = $plugin->getClientConfiguration();
    $headers = $this->createHeaders('POST', $configuration);
    $headers->tokenizationId = $token;

    $request = (new GetTokenRequest())
      ->setCheckoutTokenizationId($token);
    $paymentsApi = new TokenPaymentsApi($this->client, $configuration);

    $response = $paymentsApi
      ->requestTokenForTokenizationIdWithHttpInfo(
        $token,
        $request,
        $headers->account,
        $headers->hashAlgorithm,
        $headers->method,
        $headers->timestamp,
        $headers->nonce,
        $token,
        $this->signature(
          $configuration->getApiKey('secret'),
          $headers->toArray(),
          json_encode(ObjectSerializer::sanitizeForSerialization($request), JSON_THROW_ON_ERROR)
        )
      );
    return $this->getResponse($plugin, $response);
  }

  /**
   * {@inheritdoc}
   */
  public function tokenRevert(PaymentInterface $payment) : TokenMITPaymentResponse {
    $plugin = $this
      ->getPaymentPlugin($payment->getOrder());
    $configuration = $plugin->getClientConfiguration();
    $headers = $this->createHeaders('POST', $configuration);
    $headers->transactionId = $payment->getRemoteId();

    $response = (new TokenPaymentsApi($this->client, $configuration))
      ->tokenRevertWithHttpInfo(
        $payment->getRemoteId(),
        $headers->account,
        $headers->hashAlgorithm,
        $headers->method,
        $headers->transactionId,
        $headers->timestamp,
        $headers->nonce,
        $this->signature(
          $configuration->getApiKey('secret'),
          $headers->toArray(),
        )
      );
    return $this->getResponse($plugin, $response);
  }

  /**
   * {@inheritdoc}
   */
  public function tokenCommit(PaymentInterface $payment, Price $amount) : TokenMITPaymentResponse {
    $order = $payment->getOrder();
    $plugin = $this
      ->getPaymentPlugin($order);
    $configuration = $plugin->getClientConfiguration();
    $headers = $this->createHeaders('POST', $configuration);
    $headers->transactionId = $payment->getRemoteId();

    $tokenRequest = (new TokenPaymentRequest())
      ->setToken($payment->getRemoteId());
    $request = $this->populatePaymentRequest($tokenRequest, $payment->getOrder())
      // Override the capture amount.
      ->setAmount($this->converter->toMinorUnits($amount));

    $response = (new TokenPaymentsApi($this->client, $configuration))
      ->tokenCommitWithHttpInfo(
        $payment->getRemoteId(),
        $request,
        $headers->account,
        $headers->hashAlgorithm,
        $headers->method,
        $headers->transactionId,
        $headers->timestamp,
        $headers->nonce,
        $this->signature(
          $configuration->getApiKey('secret'),
          $headers->toArray(),
          json_encode(ObjectSerializer::sanitizeForSerialization($request), JSON_THROW_ON_ERROR)
        )
      );

    $response = $this->getResponse($plugin, $response);

    if ($response instanceof Error) {
      throw new ApiException($response->getMessage() ?: 'Failed to capture the payment. No message was given by Paytrail API.');
    }
    return $response;
  }

  /**
   * Constructs a new token payment request object.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order.
   * @param string $token
   *   The token.
   *
   * @return \Paytrail\Payment\Model\TokenPaymentRequest
   *   The token payment request.
   */
  private function populateTokenMitRequest(OrderInterface $order, string $token) : TokenPaymentRequest {
    $tokenRequest = (new TokenPaymentRequest())
      ->setToken($token);
    return $this->populatePaymentRequest($tokenRequest, $order);
  }

  /**
   * {@inheritdoc}
   */
  public function tokenMitAuthorize(OrderInterface $order, string $token) : TokenMITPaymentResponse {
    $plugin = $this->getPaymentPlugin($order);
    $configuration = $plugin->getClientConfiguration();
    $headers = $this->createHeaders('POST', $configuration);
    $request = $this->populateTokenMitRequest($order, $token);

    $response = (new TokenPaymentsApi($this->client, $configuration))
      ->tokenMitAuthorizationHoldWithHttpInfo(
        $request,
        $headers->account,
        $headers->hashAlgorithm,
        $headers->method,
        $headers->timestamp,
        $headers->nonce,
        $this->signature(
          $configuration->getApiKey('secret'),
          $headers->toArray(),
          json_encode(ObjectSerializer::sanitizeForSerialization($request), JSON_THROW_ON_ERROR)
        )
      );
    return $this->getResponse($plugin, $response);
  }

  /**
   * {@inheritdoc}
   */
  public function tokenMitCharge(OrderInterface $order, string $token) : TokenMITPaymentResponse {
    $plugin = $this->getPaymentPlugin($order);
    $configuration = $plugin->getClientConfiguration();
    $headers = $this->createHeaders('POST', $configuration);

    $request = $this->populateTokenMitRequest($order, $token);

    $response = (new TokenPaymentsApi($this->client, $configuration))
      ->tokenMitChargeWithHttpInfo(
        $request,
        $headers->account,
        $headers->hashAlgorithm,
        $headers->method,
        $headers->timestamp,
        $headers->nonce,
        $this->signature(
          $configuration->getApiKey('secret'),
          $headers->toArray(),
          json_encode(ObjectSerializer::sanitizeForSerialization($request), JSON_THROW_ON_ERROR)
        )
      );
    return $this->getResponse($plugin, $response);
  }

}