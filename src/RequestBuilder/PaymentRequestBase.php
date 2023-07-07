<?php

declare(strict_types = 1);

namespace Drupal\commerce_paytrail\RequestBuilder;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\Entity\OrderItemInterface;
use Drupal\commerce_paytrail\Event\ModelEvent;
use Drupal\commerce_paytrail\PaymentGatewayPluginTrait;
use Drupal\commerce_paytrail\Plugin\Commerce\PaymentGateway\PaytrailInterface;
use Drupal\commerce_price\Calculator;
use Drupal\commerce_price\MinorUnitsConverterInterface;
use Drupal\commerce_product\Entity\ProductVariationInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Uuid\UuidInterface;
use Paytrail\SDK\Model\CallbackUrl;
use Paytrail\SDK\Model\Customer;
use Paytrail\SDK\Model\Item;
use Paytrail\SDK\Request\AbstractPaymentRequest;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * A base class for payment requests.
 */
abstract class PaymentRequestBase {

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
   * @param int $callbackDelay
   *   The callback delay.
   */
  public function __construct(
    protected UuidInterface $uuidService,
    protected TimeInterface $time,
    protected EventDispatcherInterface $eventDispatcher,
    protected MinorUnitsConverterInterface $converter,
    protected int $callbackDelay,
  ) {
  }

  /**
   * Check if given order has any discounts applied.
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
   * @return \Paytrail\SDK\Model\Item
   *   The paytrail order item.
   */
  protected function createOrderLine(OrderItemInterface $orderItem) : Item {
    $item = (new Item())
      ->setUnitPrice($this->converter->toMinorUnits($orderItem->getAdjustedUnitPrice()))
      ->setUnits((int) $orderItem->getQuantity())
      ->setVatPercentage(0);

    if ($purchasedEntityId = $orderItem->getPurchasedEntityId()) {
      $item->setProductCode((string) $purchasedEntityId);
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
   * @param \Paytrail\SDK\Request\AbstractPaymentRequest $request
   *   The base request.
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order.
   * @param string $event
   *   The event dispatcher event.
   *
   * @return \Paytrail\SDK\Request\AbstractPaymentRequest
   *   The payment request
   *
   * @throws \Drupal\commerce_paytrail\Exception\PaytrailPluginException
   */
  protected function populatePaymentRequest(AbstractPaymentRequest $request, OrderInterface $order, string $event) : AbstractPaymentRequest {
    $plugin = $this->getPaymentPlugin($order);

    $request->setAmount($this->converter->toMinorUnits($order->getTotalPrice()))
      ->setStamp($this->uuidService->generate())
      ->setLanguage($plugin->getLanguage())
      ->setItems(array_map(
        fn (OrderItemInterface $item) => $this->createOrderLine($item),
        $order->getItems()
      ))
      // Only EUR is supported.
      ->setCurrency('EUR')
      ->setCallbackUrls((new CallbackUrl())
        ->setSuccess($plugin->getNotifyUrl()->toString())
        ->setCancel($plugin->getNotifyUrl()->toString())
      )
      // Delay callback polling to minimize the chance of Paytrail
      // calling ::onNotify() before customer returns from the payment
      // gateway.
      ->setCallbackDelay($this->callbackDelay)
      ->setRedirectUrls((new CallbackUrl())
        ->setSuccess($plugin->getReturnUrl($order)->toString())
        ->setCancel($plugin->getCancelUrl($order)->toString())
      );

    $customer = (new Customer())
      ->setEmail($order->getEmail());

    $request->setCustomer($customer);

    $this
      ->eventDispatcher
      ->dispatch(new ModelEvent(
        $request,
        order: $order,
        event: $event,
      ));

    // Paytrail does not support order level discounts, such as giftcards.
    // Remove order items if order has any discounts applied.
    // See https://www.drupal.org/project/commerce_paytrail/issues/3339269.
    if (
      $plugin->orderDiscountStrategy() === PaytrailInterface::STRATEGY_REMOVE_ITEMS &&
      $this->orderHasDiscounts($order)
    ) {
      $request->setItems(NULL);
    }
    // We use the reference field to load the order. Make sure it cannot be
    // changed.
    $request->setReference($order->id());

    return $request;
  }

}
