<?php

declare(strict_types = 1);

namespace Drupal\commerce_paytrail\Repository;

/**
 * Provides helper trait to deal with form values.
 */
trait FormTrait {

  /**
   * Array of form values.
   *
   * @var \Drupal\commerce_paytrail\Repository\FormValue[]
   */
  protected $values = [];

  /**
   * Sets the value.
   *
   * @param string $key
   *   The key.
   * @param mixed $value
   *   The value.
   * @param callable|null $formatter
   *   The formatter.
   *
   * @return $this
   *   The self.
   */
  protected function setValue(string $key, $value, callable $formatter = NULL) : self {
    $this->values[$key] = new FormValue($key, $value, $formatter);
    return $this;
  }

  /**
   * Gets the value.
   *
   * @param string $key
   *   The key.
   *
   * @return mixed
   *   The value or null.
   */
  protected function getValue(string $key) {
    if (isset($this->values[$key])) {
      return $this->values[$key]->value();
    }
    return NULL;
  }

  /**
   * Removes the given value.
   *
   * @param string $key
   *   The key.
   *
   * @return $this
   *   The self.
   */
  protected function removeValue(string $key) : self {
    unset($this->values[$key]);

    return $this;
  }

  /**
   * Builds item form array.
   *
   * @return array
   *   The build array.
   */
  abstract public function build() : array;

}
