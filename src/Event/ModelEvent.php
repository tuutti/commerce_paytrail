<?php

declare(strict_types = 1);

namespace Drupal\commerce_paytrail\Event;

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
   */
  public function __construct(
    public ModelInterface $model,
    public ?Header $headers = NULL
  ) {
  }

}
