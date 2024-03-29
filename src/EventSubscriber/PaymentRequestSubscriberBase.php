<?php

declare(strict_types = 1);

namespace Drupal\commerce_paytrail\EventSubscriber;

use Drupal\commerce_paytrail\Event\ModelEvent;
use Drupal\commerce_paytrail\Exception\PaytrailPluginException;
use Drupal\commerce_paytrail\PaymentGatewayPluginTrait;
use Paytrail\SDK\Request\AbstractPaymentRequest;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * A base class for payment request subscribers.
 */
abstract class PaymentRequestSubscriberBase implements EventSubscriberInterface {

  use PaymentGatewayPluginTrait;

  /**
   * Validates the given event.
   *
   * @param \Drupal\commerce_paytrail\Event\ModelEvent $event
   *   The event to validate.
   *
   * @return bool
   *   TRUE if event is valid.
   */
  protected function isValid(ModelEvent $event) : bool {
    if (!$event->model instanceof AbstractPaymentRequest) {
      return FALSE;
    }

    try {
      $this->getPaymentPlugin($event->order);
      return TRUE;
    }
    catch (PaytrailPluginException) {
    }
    return FALSE;
  }

  /**
   * Event callback.
   *
   * @param \Drupal\commerce_paytrail\Event\ModelEvent $event
   *   The event.
   */
  abstract public function processEvent(ModelEvent $event): void;

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() : array {
    return [
      ModelEvent::class => ['processEvent'],
    ];
  }

}
