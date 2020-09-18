<?php

namespace Drupal\commerce_paytrail\EventSubscriber;

use Drupal\commerce_paytrail\Event\FormInterfaceEvent;
use Drupal\commerce_paytrail\Event\PaytrailEvents;
use Drupal\commerce_paytrail\Repository\Product\Product;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Includes required data for paytrail form interface.
 */
final class FormAlterSubscriber implements EventSubscriberInterface {

  /**
   * Adds the billing details.
   *
   * @param \Drupal\commerce_paytrail\Event\FormInterfaceEvent $event
   *   The event to respond to.
   */
  public function addBillingDetails(FormInterfaceEvent $event) : void {
    $order = $event->getOrder();

    // Send address data only if configured.
    if (!$billing_data = $order->getBillingProfile()) {
      return;
    }
    $event->getFormInterface()
      ->setPayerEmail($order->getEmail());

    /** @var \Drupal\address\AddressInterface $address */
    if ($address = $billing_data->get('address')->first()) {
      $event->getFormInterface()->setPayerFromAddress($address);
    }
  }

  /**
   * Adds the product details.
   *
   * @param \Drupal\commerce_paytrail\Event\FormInterfaceEvent $event
   *   The event to respond to.
   */
  public function addProductDetails(FormInterfaceEvent $event) : void {
    $order = $event->getOrder();

    if (!$event->getPlugin()->collectProductDetails()) {
      return;
    }

    $taxes_included = FALSE;

    if ($order->getStore()->hasField('prices_include_tax')) {
      $taxes_included = $order->getStore()->get('prices_include_tax')->value;

      // We can only send this value when taxes are included in prices.
      if ($taxes_included) {
        $event->getFormInterface()->setIsVatIncluded(TRUE);
      }
    }

    $order_has_adjustments = $order->getAdjustments();

    if (count($order_has_adjustments) > 0) {
      // We cannot send product details if order has any adjustments because
      // Paytrail calculates the total price from individual unit prices and
      // it won't match with the actual order total price.
      // @see #3169157.
      return;
    }

    foreach ($order->getItems() as $delta => $item) {
      $product = (new Product())
        ->setQuantity((int) $item->getQuantity())
        ->setTitle($item->getTitle())
        ->setPrice($item->getAdjustedUnitPrice());

      if ($purchasedEntity = $item->getPurchasedEntity()) {
        $product->setItemId($purchasedEntity->id());
      }

      foreach ($item->getAdjustments() as $adjustment) {
        if ($adjustment->getType() === 'tax' && $taxes_included) {
          $product->setTax((float) $adjustment->getPercentage() * 100);

          continue;
        }
      }
      $event->getFormInterface()->addProduct($product);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      PaytrailEvents::FORM_ALTER => [
        ['addBillingDetails'],
        ['addProductDetails'],
      ],
    ];
  }

}
