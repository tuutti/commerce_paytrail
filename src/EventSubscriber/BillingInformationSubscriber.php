<?php

declare(strict_types = 1);

namespace Drupal\commerce_paytrail\EventSubscriber;

use Drupal\commerce_paytrail\Event\ModelEvent;
use Drupal\commerce_paytrail\PaymentGatewayPluginTrait;
use Paytrail\Payment\Model\Address;
use Paytrail\Payment\Model\PaymentRequest;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Adds billing information to payment requests when enabled.
 */
final class BillingInformationSubscriber implements EventSubscriberInterface {

  use PaymentGatewayPluginTrait;

  /**
   * Adds billing information to payment request.
   *
   * @param \Drupal\commerce_paytrail\Event\ModelEvent $event
   *   The event to subscribe to.
   */
  public function addBillingInformation(ModelEvent $event) : void {
    if (!$event->model instanceof PaymentRequest || !$order = $event->order) {
      return;
    }
    $plugin = $this->getPaymentPlugin($order);

    // Send invoice/customer data only if collect billing information
    // setting is enabled and customer has entered their address.
    if (
      !$plugin->collectsBillingInformation() ||
      /** @var \Drupal\address\AddressInterface $address */
      !$address = $order->getBillingProfile()?->get('address')->first()
    ) {
      return;
    }
    $event->model->getCustomer()
      ->setFirstName($address->getGivenName())
      ->setLastName($address->getFamilyName());

    $event->model->setInvoicingAddress(
      (new Address())
        ->setStreetAddress($address->getAddressLine1())
        ->setCity($address->getLocale())
        ->setCountry($address->getCountryCode())
        ->setPostalCode($address->getPostalCode())
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() : array {
    return [
      ModelEvent::class => ['addBillingInformation'],
    ];
  }

}
