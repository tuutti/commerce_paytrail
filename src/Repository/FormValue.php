<?php

declare(strict_types = 1);

namespace Drupal\commerce_paytrail\Repository;

/**
 * Defines the data type for form element.
 */
final class FormValue {

  protected $key;
  protected $value;

  /**
   * Constructs a new instance.
   *
   * @param string $key
   *   The form key.
   * @param string $value
   *   The form value.
   */
  public function __construct(string $key, $value) {
    $this->key = $key;
    // @todo Should we just urlencode everything?
    $this->value = str_replace('|', '', $value);
  }

  public function key() : string {
    return $this->key;
  }

  public function value() : string {
    return $this->value;
  }

}
