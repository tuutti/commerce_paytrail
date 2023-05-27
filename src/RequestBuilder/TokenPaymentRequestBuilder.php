<?php

declare(strict_types = 1);

namespace Drupal\commerce_paytrail\RequestBuilder;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_paytrail\Plugin\Commerce\PaymentGateway\PaytrailToken;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Uuid\UuidInterface;
use GuzzleHttp\ClientInterface;
use Paytrail\Payment\Api\TokenPaymentsApi;
use Paytrail\Payment\ApiException;
use Paytrail\Payment\Model\GetTokenRequest;
use Paytrail\Payment\Model\TokenizationRequestResponse;
use Paytrail\Payment\Model\TokenPaymentRequest;
use Paytrail\Payment\ObjectSerializer;
use Paytrail\SDK\Client;
use Paytrail\SDK\Model\CallbackUrl;
use Paytrail\SDK\Model\Customer;
use Paytrail\SDK\Request\MitPaymentRequest;

/**
 * The payment request builder.
 *
 * @internal
 */
final class TokenPaymentRequestBuilder extends PaymentRequestBase {

  public function createAddCardForm(PaytrailToken $plugin, OrderInterface $order) : array {
    $configuration = $plugin->getClientConfiguration();
    $headers = $this->createHeaders('POST', $configuration);

    $form = $headers->toArray();
    unset($form['platform-name']);

    $form += [
      'checkout-redirect-success-url' => $plugin->getReturnUrl($order)->toString(),
      'checkout-redirect-cancel-url' => $plugin->getCancelUrl($order)->toString(),
      'checkout-callback-success-url' => $plugin->getNotifyUrl()->toString(),
      'checkout-callback-cancel-url'  => $plugin->getNotifyUrl()->toString(),
      'language' => $plugin->getLanguage(),
    ];
    $form['signature'] = $this->signature($configuration->getApiKey('secret'), $form);
    return $form;
  }

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

  public function merchantInitiatedTransaction(PaytrailToken $plugin, OrderInterface $order, string $token) {
    $configuration = $plugin->getClientConfiguration();
    $headers = $this->createHeaders('POST', $configuration);

    /*$client = new Client((int) $configuration->getApiKey('account'), $configuration->getApiKey('secret'), '');
    $request = (new MitPaymentRequest())
      ->setStamp($this->uuidService->generate())
      ->setLanguage($plugin->getLanguage())
      ->setCurrency('EUR')
      ->setCallbackUrls(
        (new CallbackUrl())
          ->setCancel($plugin->getNotifyUrl()->toString())
          ->setSuccess($plugin->getNotifyUrl()->toString())
      )
      ->setReference($order->id())
      ->setRedirectUrls(
        (new CallbackUrl())
        ->setSuccess($plugin->getReturnUrl($order)->toString())
        ->setCancel($plugin->getCancelUrl($order)->toString())
      )
      ->setCustomer(
        (new Customer())
          ->setEmail($order->getEmail())
        )
      ->setAmount((int) $order->getTotalPrice()->getNumber());
    $request->setToken($token);
    $response = $client->createMitPaymentCharge($request);
    $x = 1;

    return;*/

    $paymentsApi = new TokenPaymentsApi($this->client, $configuration);

    $tokenRequest = (new TokenPaymentRequest())
      ->setToken($token);
    $request = $this->populateRequest($tokenRequest, $order);

    $response = $paymentsApi
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
