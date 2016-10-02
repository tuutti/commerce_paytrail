<?php

namespace Drupal\commerce_paytrail\Repository;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\GenericEvent;

/**
 * Class Methods.
 *
 * @package Drupal\commerce_paytrail\Repository
 */
class MethodRepository {

  use StringTranslationTrait;

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
   * Get list of available payment methods.
   */
  public function getMethods() {
    $methods = [
      1 => ['Nordea' => ''],
      2 => ['Osuuspankki' => ''],
      3 => ['Danske Bank' => ''],
      5 => ['Ålandsbanken' => ''],
      6 => ['Handelsbanken' => ''],
      9 => ['Paypal' => ''],
      10 => ['S-Pankki' => ''],
      11 => ['Klarna, Invoice' => ''],
      12 => ['Klarna, Instalment' => ''],
      18 => ['Jousto' => ''],
      19 => ['Collector' => ''],
      30 => ['Visa' => ''],
      31 => ['MasterCard' => ''],
      34 => ['Diners Club' => ''],
      35 => ['JCB' => ''],
      36 => ['Paytrail account' => ''],
      50 => ['Aktia' => ''],
      51 => ['POP Pankki' => ''],
      52 => ['Säästöpankki' => ''],
      53 => ['Visa (Nets)' => 'Visa'],
      54 => ['MasterCard (Nets)' => 'MasterCard'],
      55 => ['Diners Club (Nets)' => 'Diners Club'],
      56 => ['American Express (Nets)' => 'American Express'],
      57 => ['Maestro (Nets)' => 'Maestro'],
      60 => ['Collector Bank' => ''],
      61 => ['Oma Säästöpankki' => 'Oma Säästöpankki'],
    ];
    $available_methods = [];
    foreach ($methods as $id => $method) {
      foreach ($method as $label => $display_label) {
        $available_methods[$id] = new Method($id, $label, $display_label);
      }
    }
    // @todo Replace with custom event?
    $event = $this->eventDispatcher->dispatch('commerce_paytrail.method_repository', new GenericEvent($available_methods));
    $methods = $event->getSubject();

    return $methods;
  }

}
