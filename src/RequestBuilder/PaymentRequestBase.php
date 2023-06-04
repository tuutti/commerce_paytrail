<?php

declare(strict_types = 1);

namespace Drupal\commerce_paytrail\RequestBuilder;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\Entity\OrderItemInterface;
use Drupal\commerce_paytrail\Event\ModelEvent;
use Drupal\commerce_paytrail\Plugin\Commerce\PaymentGateway\PaytrailInterface;
use Drupal\commerce_price\Calculator;
use Drupal\commerce_price\MinorUnitsConverterInterface;
use Drupal\commerce_product\Entity\ProductVariationInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Uuid\UuidInterface;
use GuzzleHttp\ClientInterface;
use Paytrail\Payment\Model\Callbacks;
use Paytrail\Payment\Model\Customer;
use Paytrail\Payment\Model\Item;
use Paytrail\Payment\Model\PaymentRequest;
use Paytrail\Payment\Model\TokenPaymentRequest;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * A base class for payment requests.
 */
abstract class PaymentRequestBase extends RequestBuilderBase {

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
   * @param int $callbackDelay
   *   The callback delay.
   */
  public function __construct(
    UuidInterface $uuidService,
    TimeInterface $time,
    EventDispatcherInterface $eventDispatcher,
    protected ClientInterface $client,
    protected MinorUnitsConverterInterface $converter,
    protected int $callbackDelay,
  ) {
    parent::__construct($uuidService, $time, $eventDispatcher);
  }

  /**
   * Checks if given order has any discounts applied.
   *
   * Note: This only applies to order level discounts, such as giftcards.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order to check.
   *
   * @return bool
   *   TRUE if order has discounts.
   */
  private function orderHasDiscounts(OrderInterface $order) : bool {
    foreach ($order->getAdjustments() as $adjustment) {
      if ($adjustment->getAmount()->isNegative()) {
        return TRUE;
      }
    }
    return FALSE;
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
   * Populates the given payment request.
   *
   * @param \Paytrail\Payment\Model\PaymentRequest|\Paytrail\Payment\Model\TokenPaymentRequest $request
   *   The base request.
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order.
   *
   * @return \Paytrail\Payment\Model\PaymentRequest|\Paytrail\Payment\Model\TokenPaymentRequest
   *   The payment request
   *
   * @throws \Drupal\commerce_paytrail\Exception\PaytrailPluginException
   */
  protected function populatePaymentRequest(PaymentRequest|TokenPaymentRequest $request, OrderInterface $order) : PaymentRequest|TokenPaymentRequest {
    $plugin = $this->getPaymentPlugin($order);

    $request->setAmount($this->converter->toMinorUnits($order->getTotalPrice()))
      ->setReference($order->id())
      ->setStamp($this->uuidService->generate())
      ->setLanguage($plugin->getLanguage())
      ->setItems(array_map(
        fn (OrderItemInterface $item) => $this->createOrderLine($item),
        $order->getItems()
      ))
      // Only EUR is supported.
      ->setCurrency('EUR')
      ->setCallbackUrls(new Callbacks([
        'success' => $plugin->getNotifyUrl()->toString(),
        'cancel' => $plugin->getNotifyUrl()->toString(),
      ]))
      // Delay callback polling to minimize the chance of Paytrail
      // calling ::onNotify() before customer returns from the payment
      // gateway.
      ->setCallbackDelay($this->callbackDelay)
      ->setRedirectUrls(new Callbacks([
        'success' => $plugin->getReturnUrl($order)->toString(),
        'cancel' => $plugin->getCancelUrl($order)->toString(),
      ]));

    $customer = (new Customer())
      ->setEmail($order->getEmail());

    $request->setCustomer($customer);

    $this
      ->eventDispatcher
      ->dispatch(new ModelEvent(
        $request,
        order: $order,
        event: $request instanceof PaymentRequest ?
          PaymentRequestBuilderInterface::PAYMENT_CREATE_EVENT :
          TokenPaymentRequestBuilderInterface::TOKEN_COMMIT_EVENT,
      ));

    // Paytrail does not support order level discounts, such as giftcards.
    // Remove order items if order has any discounts applied.
    // See https://www.drupal.org/project/commerce_paytrail/issues/3339269.
    if (
      $plugin->orderDiscountStrategy() === PaytrailInterface::STRATEGY_REMOVE_ITEMS &&
      $this->orderHasDiscounts($order)
    ) {
      $request['items'] = NULL;
    }

    // We use reference field to load the order. Make sure it cannot be changed.
    if ($request->getReference() !== $order->id()) {
      throw new \LogicException('The value of "reference" field cannot be changed.');
    }

    return $request;
  }

}
