<?php

declare(strict_types = 1);

namespace Drupal\commerce_paytrail\Event;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_paytrail\Header;
use Drupal\Component\EventDispatcher\Event;
use Paytrail\Payment\Model\ModelInterface;

/**
 * Allow model data to be altered.
 */
final class ModelEvent extends Event {

  /**
   * Constructs a new instance.
   *
   * @param \Paytrail\Payment\Model\ModelInterface $model
   *   The model.
   * @param \Drupal\commerce_paytrail\Header|null $headers
   *   The header.
   * @param \Drupal\commerce_order\Entity\OrderInterface|null $order
   *   The order.
   * @param string|null $event
   *   The event.
   */
  public function __construct(
    public ModelInterface $model,
    public ?Header $headers = NULL,
    public ?OrderInterface $order = NULL,
    public ?string $event = NULL,
  ) {
  }

}
