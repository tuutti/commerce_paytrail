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
   * @param \Drupal\Component\Uuid\UuidInterface $uuidService
   *   The uuid service.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $eventDispatcher
   *   The event dispatcher.
   * @param \GuzzleHttp\ClientInterface $client
   *   The HTTP client.
   * @param \Drupal\commerce_price\MinorUnitsConverterInterface $converter
   *   The minor unit converter.
   */
  public function __construct(
    UuidInterface $uuidService,
    TimeInterface $time,
    EventDispatcherInterface $eventDispatcher,
    private ClientInterface $client,
    private MinorUnitsConverterInterface $converter
  ) {
    parent::__construct($uuidService, $time, $eventDispatcher);
  }

  /**
   * {@inheritdoc}
   */
  public function refund(string $transactionId, OrderInterface $order, Price $amount) : RefundResponse {
    $plugin = $this->getPaymentPlugin($order);
    $configuration = $plugin->getClientConfiguration();
    $headers = $this->createHeaders('POST', $configuration, transactionId: $transactionId);

    $request = $this->createRefundRequest($order, $amount, $headers->nonce);

    $this->eventDispatcher
      ->dispatch(new ModelEvent(
        $request,
        order: $order,
        event: RefundRequestBuilderInterface::REFUND_CREATE
      ));

    $response = (new PaymentsApi($this->client, $configuration))
      ->refundPaymentByTransactionIdWithHttpInfo(
        $headers->transactionId,
        $request,
        $configuration->getApiKey('account'),
        $headers->hashAlgorithm,
        $headers->method,
        $headers->transactionId,
        $headers->timestamp,
        $headers->nonce,
        $this->signature(
          $configuration->getApiKey('secret'),
          $headers->toArray(),
          json_encode(ObjectSerializer::sanitizeForSerialization($request), JSON_THROW_ON_ERROR)
        ),
      );
    return $this->getResponse($plugin, $response,
      new ModelEvent(
        $response[0],
        $headers,
        $order,
        RefundRequestBuilderInterface::REFUND_CREATE_RESPONSE
      )
    );
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

    return (new Refund())
      ->setRefundReference($order->id())
      ->setAmount($this->converter->toMinorUnits($amount))
      ->setCallbackUrls(new Callbacks([
        'success' => $plugin->getNotifyUrl('refund-success')->toString(),
        'cancel' => $plugin->getNotifyUrl('refund-cancel')->toString(),
      ]))
      ->setRefundStamp($nonce);
  }

}
