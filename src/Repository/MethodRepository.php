<?php

namespace Drupal\commerce_paytrail\Repository;

use Drupal\commerce_paytrail\Event\PaymentRepositoryEvent;
use Drupal\commerce_paytrail\Event\PaytrailEvents;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Class Methods.
 *
 * @package Drupal\commerce_paytrail\Repository
 */
class MethodRepository {

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * Creates an AddressFormatRepository instance.
   *
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The event dispatcher.
   */
  public function __construct(EventDispatcherInterface $event_dispatcher) {
    $this->eventDispatcher = $event_dispatcher;
  }

  /**
   * Get list of default payment methods.
   */
  public function getDefaultMethods() {
    $methods = [
      1 => new Method(1, 'Nordea'),
      2 => new Method(2, 'Osuuspankki'),
      3 => new Method(3, 'Danske Bank'),
      5 => new Method(5, 'Ålandsbanken'),
      6 => new Method(6, 'Handelsbanken'),
      9 => new Method(9, 'Paypal'),
      10 => new Method(10, 'S-Pankki'),
      11 => new Method(11, 'Klarna, Invoice'),
      12 => new Method(12, 'Klarna, Instalment'),
      18 => new Method(18, 'Jousto'),
      19 => new Method(19, 'Collector'),
      30 => new Method(30, 'Visa'),
      31 => new Method(31, 'MasterCard'),
      34 => new Method(34, 'Diners Club'),
      35 => new Method(35, 'JCB'),
      36 => new Method(36, 'Paytrail account'),
      50 => new Method(50, 'Aktia'),
      51 => new Method(51, 'POP Pankki'),
      52 => new Method(52, 'Säästöpankki'),
      53 => new Method(53, 'Visa (Nets)', 'Visa'),
      54 => new Method(54, 'MasterCard (Nets)', 'MasterCard'),
      55 => new Method(55, 'Diners Club (Nets)', 'Diners Club'),
      56 => new Method(56, 'American Express (Nets)', 'American Express'),
      57 => new Method(57, 'Maestro (Nets)', 'Maestro'),
      60 => new Method(60, 'Collector Bank'),
      61 => new Method(61, 'Oma Säästöpankki'),
    ];
    return $methods;
  }

  /**
   * Get list of available payment methods.
   *
   * @return array
   *   List of available payment methods.
   */
  public function getMethods() {
    $available_methods = $this->getDefaultMethods();
    /** @var PaymentRepositoryEvent $event */
    $event = $this->eventDispatcher->dispatch(PaytrailEvents::PAYMENT_REPO_ALTER, new PaymentRepositoryEvent($available_methods));
    $methods = $event->getPaymentMethods();

    return $methods;
  }

}
