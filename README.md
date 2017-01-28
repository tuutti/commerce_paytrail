#Commerce paytrail
[![Build Status](https://travis-ci.org/tuutti/commerce_paytrail.svg?branch=8.x-1.x)](https://travis-ci.org/tuutti/commerce_paytrail)

##Description
This module integrates [Paytrail](https://www.paytrail.com/en) payment method with Drupal Commerce.

##Installation
`$ composer require drupal/commerce_paytrail`

##Usage

####How to alter payment methods

Create new event subscriber that responds to \Drupal\commerce_paytrail\Events\PaytrailEvents::PAYMENT_REPO_ALTER.

####How to alter values submitted to the Paytrail

Create new event subscriber that responds to \Drupal\commerce_paytrail\Events\PaytrailEvents::TRANSACTION_REPO_ALTER.

For example:

`my_module.services.yml:`

```
services:
  my_module.transaction_values_subscriber:
    class: '\Drupal\my_module\EventSubscriber\PaytrailTransactionSubscriber'
    tags:
      - { name: 'event_subscriber' }
```
`src/EventSubscriber/PaytrailTransactionSubscriber.php:`
```php
<?php
namespace Drupal\my_module\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\commerce_paytrail\Event\PaytrailEvents as Events;
use Drupal\commerce_paytrail\Event\TransactionRepositoryEvent;
use Drupal\commerce_paytrail\Repository\PaytrailProduct;

/**
 * Event Subscriber.
 */
class PaytrailTransactionSubscriber implements EventSubscriberInterface {

  /**
   * Dispatch Events::TRANSACTION_REPO_ALTER event.
   *
   * @param \Drupal\commerce_paytrail\Event\TransactionRepositoryEvent
   *   The event to dispatch.
   */
  public function onRespond(TransactionRepositoryEvent $event) {
    // @see \Drupal\commerce_paytrail\Ȩvent\TransactionRepositoryEvent for available methods.
    /** @var \Drupal\commerce_paytrail\Repository\TransactionRepository $repository */
    $repository = $event->getTransactionRepository();

    if (my_special_case) {
      $repository->setOrderDescription('Custom order description')
      ->setOrderNumber('Custom order number');
    }
    // Here we assume that $repository is instance of \Drupal\commerce_paytrail\Repository\E1TransactionRepository.
    if (my_special_case_two) {
      $repository->setIncludeVat(1);

      $order = $event->getOrder();
      foreach ($order->getItems() as $delta => $item) {
        // @see \Drupal\commerce_paytrail\Repository\PaytrailProduct for available methods.
        $repository->setProduct($delta, new PaytrailProduct());
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[Events::PAYMENT_REPO_ALTER][] = ['onRespond'];
    return $events;
  }

}
```
