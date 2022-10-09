<?php

declare(strict_types = 1);

namespace Drupal\commerce_paytrail\RequestBuilder;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_paytrail\Event\ModelEvent;
use Drupal\commerce_price\MinorUnitsConverterInterface;
use Drupal\commerce_price\Price;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Uuid\UuidInterface;
use GuzzleHttp\ClientInterface;
use Paytrail\Payment\Api\PaymentsApi;
use Paytrail\Payment\Model\Callbacks;
use Paytrail\Payment\Model\Refund;
use Paytrail\Payment\Model\RefundResponse;
use Paytrail\Payment\ObjectSerializer;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * The refund request builder.
 *
 * @internal
 */
class RefundRequestBuilder extends RequestBuilderBase {

  /**
   * Constructs a new instance.
   *
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $eventDispatcher
   *   The event dispatcher.
   * @param \GuzzleHttp\ClientInterface $client
   *   The HTTP client.
   * @param \Drupal\Component\Uuid\UuidInterface $uuidService
   *   The uuid service.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Drupal\commerce_price\MinorUnitsConverterInterface $converter
   *   The minor unit converter.
   */
  public function __construct(
    protected EventDispatcherInterface $eventDispatcher,
    protected ClientInterface $client,
    UuidInterface $uuidService,
    TimeInterface $time,
    protected MinorUnitsConverterInterface $converter
  ) {
    parent::__construct($uuidService, $time);
  }

  /**
   * Refunds the given order and amount.
   *
   * @param string $transactionId
   *   The transaction ID.
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order to refund.
   * @param \Drupal\commerce_price\Price $amount
   *   The amount.
   *
   * @return \Paytrail\Payment\Model\RefundResponse
   *   The refund response.
   *
   * @throws \Drupal\commerce_paytrail\Exception\PaytrailPluginException
   * @throws \Drupal\commerce_paytrail\Exception\SecurityHashMismatchException
   * @throws \Paytrail\Payment\ApiException
   */
  public function refund(string $transactionId, OrderInterface $order, Price $amount) : RefundResponse {
    $configuration = $this
      ->getPaymentPlugin($order)
      ->getClientConfiguration();
    $headers = $this->createHeaders('POST', $configuration, $transactionId);

    $request = $this->createRefundRequest($order, $amount, $headers->nonce);

    $response = (new PaymentsApi($this->client, $configuration))
      ->refundPaymentByTransactionIdWithHttpInfo(
        $transactionId,
        $request,
        $configuration->getApiKey('account'),
        $headers->hashAlgorithm,
        $headers->method,
        $transactionId,
        $headers->timestamp,
        $headers->nonce,
        $this->signature(
          $configuration->getApiKey('secret'),
          $headers->toArray(),
          json_encode(ObjectSerializer::sanitizeForSerialization($request), JSON_THROW_ON_ERROR)
        ),
      );
    return $this->getResponse($order, $response);
  }

  /**
   * Creates a new refund request object.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order.
   * @param \Drupal\commerce_price\Price $amount
   *   The amount.
   * @param string $nonce
   *   The nonce.
   *
   * @return \Paytrail\Payment\Model\Refund
   *   The refund request model.
   *
   * @throws \Drupal\commerce_paytrail\Exception\PaytrailPluginException
   */
  public function createRefundRequest(
    OrderInterface $order,
    Price $amount,
    string $nonce
  ) : Refund {
    $plugin = $this->getPaymentPlugin($order);

    $request = (new Refund())
      ->setRefundReference($order->id())
      ->setAmount($this->converter->toMinorUnits($amount))
      ->setCallbackUrls(new Callbacks([
        'success' => $plugin->getNotifyUrl('refund')->toString(),
        'cancel' => $plugin->getNotifyUrl('refund')->toString(),
      ]))
      ->setRefundStamp($nonce);

    $this->eventDispatcher
      ->dispatch(new ModelEvent($request));

    return $request;
  }

}
