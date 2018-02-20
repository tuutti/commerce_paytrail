<?php

namespace Drupal\commerce_paytrail\EventSubscriber;

use Drupal\commerce_paytrail\Event\FormInterfaceEvent;
use Drupal\commerce_paytrail\Event\PaytrailEvents;
use Drupal\commerce_paytrail\Exception\InvalidBillingException;
use Drupal\commerce_paytrail\Plugin\Commerce\PaymentGateway\PaytrailBase;
use Drupal\commerce_paytrail\Repository\Product\Product;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Includes required data for paytrail form interface.
 */
class FormAlterSubscriber implements EventSubscriberInterface {

  protected $moduleHandler;

  /**
   * Constructs a new instance.
   *
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler.
   */
  public function __construct(ModuleHandlerInterface $moduleHandler) {
    $this->moduleHandler = $moduleHandler;
  }

  /**
   * Adds the billing details.
   *
   * @param \Drupal\commerce_paytrail\Event\FormInterfaceEvent $event
   *   The event to respond to.
   */
  public function addBillingDetails(FormInterfaceEvent $event) : void {
    $order = $event->getOrder();

    // Send address data only if configured.
    if (!$event->getPlugin()->isDataIncluded(PaytrailBase::PAYER_DETAILS)) {
      return;
    }

    if (!$billing_data = $order->getBillingProfile()) {
      throw new InvalidBillingException('Invalid billing data for ' . $order->id());
    }
    /** @var \Drupal\address\AddressInterface $address */
    $address = $billing_data->get('address')->first();

    $event->getFormInterface()
      ->setPayerEmail($order->getEmail())
      ->setPayerFromAddress($address);
  }

  /**
   * Adds the product details.
   *
   * @param \Drupal\commerce_paytrail\Event\FormInterfaceEvent $event
   *   The event to respond to.
   */
  public function addProductDetails(FormInterfaceEvent $event) : void {
    $order = $event->getOrder();

    if (!$event->getPlugin()->isDataIncluded(PaytrailBase::PRODUCT_DETAILS)) {
      return;
    }

    $taxes_included = FALSE;

    if ($this->moduleHandler->moduleExists('commerce_tax')) {
      $taxes_included = $order->getStore()->get('prices_include_tax')->value;

      // We can only send this value when taxes are enabled.
      if ($taxes_included) {
        $event->getFormInterface()->setIsVatIncluded(TRUE);
      }
    }

    foreach ($order->getItems() as $delta => $item) {
      $product = Product::createFromOrderItem($item);

      foreach ($item->getAdjustments() as $adjustment) {
        if ($adjustment->getType() === 'tax' && $taxes_included) {
          $product->setTax((float) $adjustment->getPercentage() * 100);
        }

        if ($adjustment->getType() === 'promotion') {
          // $product->setDiscount((float) $adjustment->getPercentage() * 100);
        }
      }
      $event->getFormInterface()->setProduct($product);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events = [
      PaytrailEvents::FORM_ALTER => [
        ['addBillingDetails'],
        ['addProductDetails'],
      ],
    ];
    return $events;
  }

}
