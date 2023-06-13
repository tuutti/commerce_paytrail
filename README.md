# Commerce paytrail

![CI](https://github.com/tuutti/commerce_paytrail/workflows/CI/badge.svg)

This module integrates [Paytrail](https://www.paytrail.com/en) payment method with Drupal Commerce.

 * For a full description of the module, visit the project page:
   https://www.drupal.org/project/commerce_paytrail

 * To submit bug reports and feature suggestions, or to track changes:
   https://www.drupal.org/project/issues/commerce_paytrail

## Installation

Install the Commerce paytrail module as you would normally install a contributed
Drupal module. Visit https://www.drupal.org/node/1897420 for further
information.

## Configuration

1. Configure the Commerce Paytrail gateway from the Administration > Commerce >
   Configuration > Payment Gateways (`/admin/commerce/config/payment-gateways`),
   by editing an existing or adding a new payment gateway.
2. Select `Paytrail` or `Paytrail (Credit card)` for the payment gateway plugin.
   * `Mode`: enables the Paytrail payment gateway in test or live mode.
   * `Account`: provide your Paytrail account.
   * `Secret`: provide your Paytrail secret.
   * `Order discount strategy`: Choose the order discount strategy.
3. Click Save to save your configuration.

## Documentation

@todo fill this.

### Respond to or alter Paytrail API requests/responses

Create an event subscriber that responds to `\Drupal\commerce_paytrail\Event\ModelEvent::class` events:
```php

class YourEventSubscriber implements \Symfony\Component\EventDispatcher\EventSubscriberInterface {

  /**
   * Event callback.
   *
   * @param \Drupal\commerce_paytrail\Event\ModelEvent $event
   *   The event.
   */
  public function processEvent(ModelEvent $event): void {
    // See below for all available events.
    if ($event->event === \Drupal\commerce_paytrail\RequestBuilder\PaymentRequestBuilderInterface::PAYMENT_CREATE_EVENT) {
      // Do something based on event.
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() : array {
    return [
      ModelEvent::class => ['processEvent'],
    ];
  }
}
```

See https://www.drupal.org/docs/develop/creating-modules/subscribe-to-and-dispatch-events.

### Available events

The event name in `\Drupal\commerce_paytrail\Event\ModelEvent`.

#### Payment requests
See [src/RequestBuilder/PaymentRequestBuilderInterface.php](src/RequestBuilder/PaymentRequestBuilderInterface.php).

- `\Drupal\commerce_paytrail\RequestBuilder\PaymentRequestBuilderInterface::PAYMENT_GET_RESPONSE_EVENT`: Respond to a successful `PaymentRequestBuilder::get` request
- `\Drupal\commerce_paytrail\RequestBuilder\PaymentRequestBuilderInterface::PAYMENT_CREATE_EVENT`: Allows payment create request to be altered. The request will return available payment methods show on Payment page
- `\Drupal\commerce_paytrail\RequestBuilder\PaymentRequestBuilderInterface::PAYMENT_CREATE_RESPONSE_EVENT`: Respond to a successful payment create request

#### Refund requests
See [src/RequestBuilder/RefundRequestBuilderInterface.php](src/RequestBuilder/RefundRequestBuilderInterface.php).

- `\Drupal\commerce_paytrail\RequestBuilder\RefundRequestBuilderInterface::REFUND_CREATE`: Allows `RefundRequestBuilder::refund` request to be altered
- `\Drupal\commerce_paytrail\RequestBuilder\RefundRequestBuilderInterface::REFUND_CREATE_RESPONSE`: Respond to a successful refund request

#### Token payment requests

See [src/RequestBuilder/TokenPaymentRequestBuilderInterface.php](src/RequestBuilder/TokenPaymentRequestBuilderInterface.php).

@todo fill these


### Prevent saved payment method from being deleted

```php
/**
 * Implements hook_entity_predelete().
 */
function hook_entity_predelete(\Drupal\Core\Entity\EntityInterface $entity) : void {
  if (condition) {
    throw new \Drupal\commerce_payment\Exception\PaymentGatewayException('Card cannot be deleted').
  }
}
```

## Maintainers

* tuutti (tuutti) - https://www.drupal.org/u/tuutti
