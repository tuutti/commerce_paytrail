<?php

declare(strict_types = 1);

namespace Drupal\commerce_paytrail\RequestBuilder;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\Entity\OrderItemInterface;
use Drupal\commerce_paytrail\Event\ModelEvent;
use Drupal\commerce_price\Calculator;
use Drupal\commerce_price\MinorUnitsConverterInterface;
use Drupal\commerce_product\Entity\ProductVariationInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Uuid\UuidInterface;
use GuzzleHttp\ClientInterface;
use Paytrail\Payment\Api\PaymentsApi;
use Paytrail\Payment\Model\Address;
use Paytrail\Payment\Model\Callbacks;
use Paytrail\Payment\Model\Customer;
use Paytrail\Payment\Model\Item;
use Paytrail\Payment\Model\Payment;
use Paytrail\Payment\Model\PaymentRequest;
use Paytrail\Payment\Model\PaymentRequestResponse;
use Paytrail\Payment\ObjectSerializer;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * The payment request builder.
 *
 * @internal
 */
final class PaymentRequestBuilder extends RequestBuilderBase implements PaymentRequestBuilderInterface {

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
   * Creates a Paytrail order item for given commerce order item.
   *
   * @param \Drupal\commerce_order\Entity\OrderItemInterface $orderItem
   *   The commerce order item.
   *
   * @return \Paytrail\Payment\Model\Item
   *   The paytrail order item.
   */
  protected function createOrderLine(OrderItemInterface $orderItem) : Item {
    $item = (new Item())
      ->setUnitPrice($this->converter->toMinorUnits($orderItem->getAdjustedUnitPrice()))
      ->setUnits((int) $orderItem->getQuantity())
      ->setVatPercentage(0);

    if ($purchasedEntityId = $orderItem->getPurchasedEntityId()) {
      $item->setProductCode($purchasedEntityId);
    }

    if ($taxes = $orderItem->getAdjustments(['tax'])) {
      $item->setVatPercentage((int) Calculator::multiply(
        reset($taxes)->getPercentage(),
        '100'
      ));
    }

    // Override default product code with SKU if possible.
    if ($orderItem->getPurchasedEntity() instanceof ProductVariationInterface) {
      $item->setProductCode($orderItem->getPurchasedEntity()->getSku());
    }

    return $item;
  }

  /**
   * {@inheritdoc}
   */
  public function get(string $transactionId, OrderInterface $order) : Payment {
    $configuration = $this->getPaymentPlugin($order)->getClientConfiguration();
    $headers = $this->createHeaders('GET', $configuration, $transactionId);

    $response = (new PaymentsApi($this->client, $configuration))
      ->getPaymentByTransactionIdWithHttpInfo(
        $transactionId,
        $configuration->getApiKey('account'),
        $headers->hashAlgorithm,
        $headers->method,
        $transactionId,
        $headers->timestamp,
        $headers->nonce,
        $this->signature(
          $configuration->getApiKey('secret'),
          $headers->toArray(),
        ),
      );
    return $this->getResponse($order, $response);
  }

  /**
   * {@inheritdoc}
   */
  public function create(OrderInterface $order) : PaymentRequestResponse {
    $configuration = $this->getPaymentPlugin($order)
      ->getClientConfiguration();
    $headers = $this->createHeaders('POST', $configuration);
    $request = $this->createPaymentRequest($order);

    $response = (new PaymentsApi($this->client, $configuration))
      ->createPaymentWithHttpInfo(
        $request,
        $configuration->getApiKey('account'),
        $headers->hashAlgorithm,
        $headers->method,
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
  public function createPaymentRequest(OrderInterface $order) : PaymentRequest {
    $plugin = $this->getPaymentPlugin($order);

    $request = (new PaymentRequest())
      ->setAmount($this->converter->toMinorUnits($order->getTotalPrice()))
      ->setReference($order->id())
      ->setStamp($this->uuidService->generate())
      ->setLanguage($plugin->getLanguage())
      ->setItems(
        array_map(
          fn (OrderItemInterface $item) => $this->createOrderLine($item),
          $order->getItems()
        )
      )
      // Only EUR is supported.
      ->setCurrency('EUR')
      ->setCallbackUrls(new Callbacks([
        'success' => $plugin->getNotifyUrl()->toString(),
        'cancel' => $plugin->getNotifyUrl()->toString(),
      ]))
      ->setRedirectUrls(new Callbacks([
        'success' => $plugin->getReturnUrl($order)->toString(),
        'cancel' => $plugin->getCancelUrl($order)->toString(),
      ]));

    $customer = (new Customer())
      ->setEmail($order->getEmail());

    $request->setCustomer($customer);

    $this->eventDispatcher
      ->dispatch(new ModelEvent($request, order: $order));

    // We use reference field to load the order. Make sure it cannot be changed.
    if ($request->getReference() !== $order->id()) {
      throw new \LogicException('The value of "reference" field cannot be changed.');
    }

    return $request;
  }

}
