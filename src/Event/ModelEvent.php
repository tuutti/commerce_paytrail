<?php

declare(strict_types = 1);

namespace Drupal\commerce_paytrail\Event;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\Component\EventDispatcher\Event;

/**
 * Allow model data to be altered.
 */
final class ModelEvent extends Event {

  /**
   * Constructs a new instance.
   *
   * @param mixed $model
   *   The model.
   * @param \Drupal\commerce_order\Entity\OrderInterface|null $order
   *   The order.
   * @param string|null $event
   *   The event.
   */
  public function __construct(
    public mixed $model,
    public ?OrderInterface $order = NULL,
    public ?string $event = NULL,
  ) {
  }

}
