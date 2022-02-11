<?php

declare(strict_types = 1);

namespace Drupal\commerce_paytrail\RequestBuilder;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\Entity\OrderItemInterface;
use Drupal\commerce_paytrail\Event\ModelEvent;
use Drupal\commerce_paytrail\Exception\SecurityHashMismatchException;
use Drupal\commerce_price\Calculator;
use Drupal\commerce_price\MinorUnitsConverterInterface;
use Drupal\commerce_price\Price;
use Drupal\commerce_product\Entity\ProductVariationInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Uuid\UuidInterface;
use GuzzleHttp\ClientInterface;
use Paytrail\Payment\Api\PaymentsApi;
use Paytrail\Payment\ApiException;
use Paytrail\Payment\Model\Address;
use Paytrail\Payment\Model\Callbacks;
use Paytrail\Payment\Model\Customer;
use Paytrail\Payment\Model\Item;
use Paytrail\Payment\Model\Payment;
use Paytrail\Payment\Model\PaymentRequest;
use Paytrail\Payment\Model\PaymentRequestResponse;
use Paytrail\Payment\Model\Refund;
use Paytrail\Payment\Model\RefundResponse;
use Paytrail\Payment\ObjectSerializer;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * The payment request builder.
 *
 * @internal
 */
class PaymentRequestBuilder extends RequestBuilderBase {

  public const TRANSACTION_ID_KEY = 'commerce_paytrail_transaction_id';
  public const STAMP_KEY = 'commerce_paytrail_stamp';

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
   *   The minor units converter.
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
      ->setVatPercentage(0)
      ->setProductCode($orderItem->getPurchasedEntityId());

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
   * Refunds the given order and amount.
   *
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
  public function refund(OrderInterface $order, Price $amount) : RefundResponse {
    if (!$transactionId = $order->getData(static::TRANSACTION_ID_KEY)) {
      throw new ApiException('No transaction id found for: ' . $order->id());
    }
    $plugin = $this->getPlugin($order);
    $configuration = $this->getPlugin($order)->getClientConfiguration();
    $headers = $this->getHeaders('POST', $configuration, $transactionId);

    $request = (new Refund())
      ->setRefundReference($order->id())
      ->setAmount($this->converter->toMinorUnits($amount))
      ->setCallbackUrls(new Callbacks([
        'success' => $plugin->getNotifyUrl()->toString(),
        'cancel' => $plugin->getNotifyUrl()->toString(),
      ]))
      ->setRefundStamp($headers->nonce);

    $this->eventDispatcher
      ->dispatch(new ModelEvent($request, $headers));

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
          \GuzzleHttp\json_encode(ObjectSerializer::sanitizeForSerialization($request))
        ),
      );
    return $this->getResponse($order, $response);
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
    if (!$transactionId = $order->getData(static::TRANSACTION_ID_KEY)) {
      throw new ApiException('No transaction id found for: ' . $order->id());
    }
    $configuration = $this->getPlugin($order)->getClientConfiguration();
    $headers = $this->getHeaders('GET', $configuration, $transactionId);

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
  public function create(
    OrderInterface $order
  ) : PaymentRequestResponse {
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

    $configuration = $plugin->getClientConfiguration();
    $headers = $this->getHeaders('POST', $configuration);

    $this->eventDispatcher
      ->dispatch(new ModelEvent($request, $headers));

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
    $order->setData(static::TRANSACTION_ID_KEY, $response->getTransactionId())
      ->setData(static::STAMP_KEY, $request->getStamp())
      ->save();

    return $response;
  }

  /**
   * Checks if returned stamp matches with stored one.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order to check.
   * @param string $expectedStamp
   *   The expected stamp.
   *
   * @return $this
   *   The self.
   *
   * @throws \Drupal\commerce_paytrail\Exception\SecurityHashMismatchException
   */
  public function validateStamp(OrderInterface $order, string $expectedStamp) : self {
    if ((!$stamp = $order->getData(static::STAMP_KEY)) || $stamp !== $expectedStamp) {
      throw new SecurityHashMismatchException('Stamp validation failed.');
    }
    return $this;
  }

}
