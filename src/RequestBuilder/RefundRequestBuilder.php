<?php

declare(strict_types = 1);

namespace Drupal\commerce_paytrail\RequestBuilder;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_paytrail\Event\ModelEvent;
use Drupal\commerce_paytrail\PaymentGatewayPluginTrait;
use Drupal\commerce_price\MinorUnitsConverterInterface;
use Drupal\commerce_price\Price;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Uuid\UuidInterface;
use Paytrail\SDK\Model\CallbackUrl;
use Paytrail\SDK\Request\RefundRequest;
use Paytrail\SDK\Response\RefundResponse;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * The refund request builder.
 *
 * @internal
 */
final class RefundRequestBuilder implements RefundRequestBuilderInterface {

  use PaymentGatewayPluginTrait;

  /**
   * Constructs a new instance.
   *
   * @param \Drupal\Component\Uuid\UuidInterface $uuidService
   *   The uuid service.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $eventDispatcher
   *   The event dispatcher.
   * @param \Drupal\commerce_price\MinorUnitsConverterInterface $converter
   *   The minor unit converter.
   */
  public function __construct(
    private UuidInterface $uuidService,
    private TimeInterface $time,
    private EventDispatcherInterface $eventDispatcher,
    private MinorUnitsConverterInterface $converter
  ) {
  }

  /**
   * Constructs a refund request.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order.
   * @param \Drupal\commerce_price\Price $amount
   *   The price.
   *
   * @return \Paytrail\SDK\Request\RefundRequest
   *   The refund request.
   */
  public function createRefundRequest(OrderInterface $order, Price $amount) : RefundRequest {
    $plugin = $this->getPaymentPlugin($order);

    return (new RefundRequest())
      ->setRefundReference($order->id())
      ->setAmount($this->converter->toMinorUnits($amount))
      ->setCallbackUrls((new CallbackUrl())
        ->setSuccess($plugin->getNotifyUrl(['event' => 'refund-success'])->toString())
        ->setCancel($plugin->getNotifyUrl(['event' => 'refund-cancel'])->toString())
      )
      ->setRefundStamp($this->uuidService->generate());
  }

  /**
   * {@inheritdoc}
   */
  public function refund(string $transactionId, OrderInterface $order, Price $amount) : RefundResponse {
    $plugin = $this->getPaymentPlugin($order);

    $request = $this->createRefundRequest($order, $amount);
    $this->eventDispatcher
      ->dispatch(new ModelEvent($request, $order, RefundRequestBuilderInterface::REFUND_CREATE));

    $response = $plugin->getClient()
      ->refund($request, $transactionId);

    $this->eventDispatcher->dispatch(new ModelEvent($response, $order, RefundRequestBuilderInterface::REFUND_CREATE_RESPONSE));

    return $response;
  }

}
