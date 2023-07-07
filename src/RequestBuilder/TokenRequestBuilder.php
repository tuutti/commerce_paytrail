<?php

declare(strict_types = 1);

namespace Drupal\commerce_paytrail\RequestBuilder;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_payment\Entity\PaymentInterface;
use Drupal\commerce_paytrail\Event\ModelEvent;
use Drupal\commerce_paytrail\Plugin\Commerce\PaymentGateway\PaytrailToken;
use Drupal\commerce_price\Price;
use Paytrail\SDK\Client;
use Paytrail\SDK\Request\AddCardFormRequest;
use Paytrail\SDK\Request\GetTokenRequest;
use Paytrail\SDK\Request\MitPaymentRequest;
use Paytrail\SDK\Request\RevertPaymentAuthHoldRequest;
use Paytrail\SDK\Response\GetTokenResponse;
use Paytrail\SDK\Response\MitPaymentResponse;
use Paytrail\SDK\Response\RevertPaymentAuthHoldResponse;
use Paytrail\SDK\Util\Signature;

/**
 * The token payment request builder.
 *
 * @internal
 */
final class TokenRequestBuilder extends PaymentRequestBase implements TokenRequestBuilderInterface {

  /**
   * {@inheritdoc}
   */
  public function createAddCardFormForOrder(OrderInterface $order) : array {
    $plugin = $this->getPaymentPlugin($order);

    $request = (new AddCardFormRequest())
      ->setLanguage($plugin->getLanguage())
      ->setCheckoutAccount($plugin->getAccount())
      ->setCheckoutAlgorithm('sha256')
      ->setCheckoutTimestamp((string) $this->time->getCurrentTime())
      ->setCheckoutNonce($this->uuidService->generate())
      ->setCheckoutMethod('POST')
      ->setCheckoutRedirectSuccessUrl($plugin->getReturnUrl($order)->toString())
      ->setCheckoutRedirectCancelUrl($plugin->getCancelUrl($order)->toString())
      ->setCheckoutCallbackSuccessUrl($plugin->getNotifyUrl()->toString())
      ->setCheckoutCallbackCancelUrl($plugin->getNotifyUrl()->toString());

    $this->eventDispatcher
      ->dispatch(new ModelEvent(
        $request,
        $order,
        TokenRequestBuilderInterface::TOKEN_ADD_CARD_FORM_EVENT
      ));

    $signature = Signature::calculateHmac(
      $request->toArray(),
      secretKey: $plugin->getSecret()
    );
    $request->setSignature($signature);

    return [
      'uri' => Client::API_ENDPOINT . '/tokenization/addcard-form',
      'data' => $request->toArray(),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getCardForToken(PaytrailToken $plugin, string $token) : GetTokenResponse {
    $request = (new GetTokenRequest())
      ->setCheckoutTokenizationId($token);

    $this->eventDispatcher
      ->dispatch(new ModelEvent(
        $request,
        event: TokenRequestBuilderInterface::TOKEN_GET_CARD_EVENT
      ));
    $response = $plugin->getClient()
      ->createGetTokenRequest($request);

    $this->eventDispatcher
      ->dispatch(new ModelEvent(
        $request,
        event: TokenRequestBuilderInterface::TOKEN_GET_CARD_RESPONSE_EVENT
      ));
    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function tokenRevert(PaymentInterface $payment) : RevertPaymentAuthHoldResponse {
    $plugin = $this
      ->getPaymentPlugin($payment->getOrder());

    $request = (new RevertPaymentAuthHoldRequest())
      ->setTransactionId($payment->getRemoteId());
    $response = $plugin->getClient()->revertPaymentAuthorizationHold($request);

    $this->eventDispatcher
      ->dispatch(new ModelEvent(
        $response,
        $payment->getOrder(),
        TokenRequestBuilderInterface::TOKEN_REVERT_RESPONSE_EVENT
      ));

    return $response;
  }

  /**
   * Constructs a new request object.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order.
   * @param string $token
   *   The token.
   * @param string $event
   *   The event.
   *
   * @return \Paytrail\SDK\Request\MitPaymentRequest
   *   The mit payment request.
   */
  public function createTokenPaymentRequest(OrderInterface $order, string $token, string $event) : MitPaymentRequest {
    $request = new MitPaymentRequest();
    $request->setToken($token);

    return $this
      ->populatePaymentRequest($request, $order, $event);
  }

  /**
   * {@inheritdoc}
   */
  public function tokenCommit(PaymentInterface $payment, Price $amount) : MitPaymentResponse {
    return $this->createMitPaymentAction(
      $payment->getOrder(),
      $payment->getRemoteId(),
      TokenRequestBuilderInterface::TOKEN_COMMIT_EVENT,
      TokenRequestBuilderInterface::TOKEN_COMMIT_RESPONSE_EVENT,
      function (Client $client, MitPaymentRequest $request) : MitPaymentResponse {
        return $client->createMitPaymentCommit($request, $request->getToken());
      }
    );
  }

  /**
   * {@inheritdoc}
   */
  public function tokenMitAuthorize(OrderInterface $order, string $token) : MitPaymentResponse {
    return $this->createMitPaymentAction(
      $order,
      $token,
      TokenRequestBuilderInterface::TOKEN_MIT_AUTHORIZE_EVENT,
      TokenRequestBuilderInterface::TOKEN_MIT_AUTHORIZE_RESPONSE_EVENT,
      function (Client $client, MitPaymentRequest $request) : MitPaymentResponse {
        return $client->createMitPaymentAuthorizationHold($request);
      }
    );
  }

  /**
   * {@inheritdoc}
   */
  public function tokenMitCharge(OrderInterface $order, string $token) : MitPaymentResponse {
    return $this->createMitPaymentAction(
      $order,
      $token,
      TokenRequestBuilderInterface::TOKEN_MIT_CHARGE_EVENT,
      TokenRequestBuilderInterface::TOKEN_MIT_CHARGE_RESPONSE_EVENT,
      function (Client $client, MitPaymentRequest $request) : MitPaymentResponse {
        return $client->createMitPaymentCharge($request);
      }
    );
  }

  /**
   * Populates a MIT payment request and performs the API call.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order.
   * @param string $token
   *   The token.
   * @param string $requestEvent
   *   The request event.
   * @param string $responseEvent
   *   The response event.
   * @param callable $responseCallback
   *   The callback.
   *
   * @return \Paytrail\SDK\Response\MitPaymentResponse
   *   The payment response.
   */
  private function createMitPaymentAction(OrderInterface $order, string $token, string $requestEvent, string $responseEvent, callable $responseCallback) : MitPaymentResponse {
    $plugin = $this
      ->getPaymentPlugin($order);

    $response = $responseCallback($plugin->getClient(), $this->createTokenPaymentRequest($order, $token, $requestEvent));

    $this->eventDispatcher
      ->dispatch(new ModelEvent(
        $response,
        $order,
        $responseEvent
      ));
    return $response;
  }

}
