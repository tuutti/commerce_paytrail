<?php

declare(strict_types = 1);

namespace Drupal\commerce_paytrail\Commerce\Shipping;

use Drupal\commerce_paytrail\Event\ModelEvent;
use Drupal\commerce_price\Calculator;
use Drupal\commerce_price\MinorUnitsConverterInterface;
use Paytrail\Payment\Model\Item;
use Paytrail\Payment\Model\PaymentRequest;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Provides a shipping support.
 */
final class ShippingEventSubscriber implements EventSubscriberInterface {

  /**
   * Constructs a new instance.
   *
   * @param \Drupal\commerce_price\MinorUnitsConverterInterface $converter
   *   The minor unit converter.
   */
  public function __construct(
    private MinorUnitsConverterInterface $converter
  ) {
  }

  /**
   * Subscribes to model event.
   *
   * @param \Drupal\commerce_paytrail\Event\ModelEvent $event
   *   The event to subscribe to.
   */
  public function addShipping(ModelEvent $event) : void {
    if (!$event->model instanceof PaymentRequest || !$order = $event->order) {
      return;
    }

    if (!$order->hasField('shipments') || $order->get('shipments')->isEmpty()) {
      return;
    }
    /** @var \Drupal\commerce_shipping\Entity\ShipmentInterface[] $shipments */
    $shipments = $order->get('shipments')->referencedEntities();

    $items = $event->model->getItems();

    foreach ($shipments as $shipment) {
      $item = (new Item())
        ->setUnitPrice($this->converter->toMinorUnits($shipment->getAdjustedAmount()))
        ->setProductCode($shipment->getShippingMethod()->getPlugin()->getPluginId())
        ->setUnits(1)
        ->setVatPercentage(0);

      if ($taxes = $shipment->getAdjustments(['tax'])) {
        $item->setVatPercentage((int) Calculator::multiply(
          reset($taxes)->getPercentage(),
          '100'
        ));
      }
      $items[] = $item;
    }
    $event->model->setItems($items);
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() : array {
    return [
      ModelEvent::class => ['addShipping'],
    ];
  }

}
