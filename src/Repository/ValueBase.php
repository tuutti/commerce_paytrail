<?php

declare(strict_types = 1);

namespace Drupal\commerce_paytrail\Repository;

/**
 * Defines the value base.
 *
 * @deprecated in commerce_paytrail:2.5.0 and is removed from commerce_paytrail:3.0.0.
 * Use \Drupal\commerce_paytrail\Respository\FormTrait instead.
 *
 * @see https://www.drupal.org/project/commerce_paytrail/issues/3181572
 */
abstract class ValueBase {

  use FormTrait;

}
