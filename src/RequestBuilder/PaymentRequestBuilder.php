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
class PaymentRequestBuilder extends RequestBuilderBase {

  use TransactionIdTrait;
  use StampKeyTrait;

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
   * Gets the payment for given order.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order.
   *
   * @return \Paytrail\Payment\Model\Payment
   *   The payment.
   *
   * @throws \Drupal\commerce_paytrail\Exception\PaytrailPluginException
   * @throws \Drupal\commerce_paytrail\Exception\SecurityHashMismatchException
   * @throws \Paytrail\Payment\ApiException
   */
  public function get(OrderInterface $order) : Payment {
    $transactionId = $this->getTransactionId($order);
    $configuration = $this->getPlugin($order)->getClientConfiguration();
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
   * Creates a new payment request.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order.
   *
   * @return \Paytrail\Payment\Model\PaymentRequestResponse
   *   The payment request response.
   *
   * @throws \Drupal\commerce_paytrail\Exception\PaytrailPluginException
   * @throws \Drupal\commerce_paytrail\Exception\SecurityHashMismatchException
   * @throws \Paytrail\Payment\ApiException
   */
  public function create(OrderInterface $order) : PaymentRequestResponse {
    $configuration = $this->getPlugin($order)
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
          \GuzzleHttp\json_encode(ObjectSerializer::sanitizeForSerialization($request))
        ),
      );
    /** @var \Paytrail\Payment\Model\PaymentRequestResponse $response */
    $response = $this->getResponse($order, $response);

    // Save stamp and transaction id for later validation.
    $this
      ->setTransactionId($order, $response->getTransactionId())
      ->setStamp($order, $request->getStamp());
    $order->save();

    return $response;
  }

  /**
   * Creates a new payment request object.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order.
   *
   * @return \Paytrail\Payment\Model\PaymentRequest
   *   The payment request.
   *
   * @throws \Drupal\Core\TypedData\Exception\MissingDataException
   * @throws \Drupal\commerce_paytrail\Exception\PaytrailPluginException
   */
  public function createPaymentRequest(OrderInterface $order) : PaymentRequest {
    $plugin = $this->getPlugin($order);

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

    // Send invoice/customer data only if collect billing information
    // setting is enabled.
    if ($plugin->collectsBillingInformation()) {
      /** @var \Drupal\address\AddressInterface $address */
      if ($address = $order->getBillingProfile()?->get('address')->first()) {
        $customer->setFirstName($address->getGivenName())
          ->setLastName($address->getFamilyName());

        $request->setInvoicingAddress(
          (new Address())
            ->setStreetAddress($address->getAddressLine1())
            ->setCity($address->getLocale())
            ->setCountry($address->getCountryCode())
            ->setPostalCode($address->getPostalCode())
        );
      }
    }
    $request->setCustomer($customer);

    $this->eventDispatcher
      ->dispatch(new ModelEvent($request));

    return $request;
  }

}
