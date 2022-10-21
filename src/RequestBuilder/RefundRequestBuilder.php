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
final class RefundRequestBuilder extends RequestBuilderBase implements RefundRequestBuilderInterface {

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
   * {@inheritdoc}
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
   * {@inheritdoc}
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
        'success' => $plugin->getNotifyUrl('refund-success')->toString(),
        'cancel' => $plugin->getNotifyUrl('refund-cancel')->toString(),
      ]))
      ->setRefundStamp($nonce);

    $this->eventDispatcher
      ->dispatch(new ModelEvent($request, order: $order));

    return $request;
  }

}
