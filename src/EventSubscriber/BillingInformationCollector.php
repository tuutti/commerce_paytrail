<?php

declare(strict_types = 1);

namespace Drupal\commerce_paytrail\EventSubscriber;

use Drupal\commerce_paytrail\Event\ModelEvent;
use Paytrail\SDK\Model\Address;

/**
 * Adds billing information to payment requests when enabled.
 */
final class BillingInformationCollector extends PaymentRequestSubscriberBase {

  /**
   * Adds billing information to payment request.
   *
   * @param \Drupal\commerce_paytrail\Event\ModelEvent $event
   *   The event to subscribe to.
   */
  public function processEvent(ModelEvent $event) : void {
    if (!$this->isValid($event)) {
      return;
    }
    $plugin = $this->getPaymentPlugin($event->order);

    // Send invoice/customer data only if collect billing information
    // setting is enabled and customer has entered their address.
    if (
      !$plugin->collectsBillingInformation() ||
      /** @var \Drupal\address\AddressInterface $address */
      !$address = $event->order->getBillingProfile()?->get('address')->first()
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

}
