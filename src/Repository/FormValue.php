<?php

declare(strict_types = 1);

namespace Drupal\commerce_paytrail\Repository;

/**
 * Defines the data type for form element.
 */
final class FormValue {

  protected $key;
  protected $value;
  protected $formatter;

  /**
   * Constructs a new instance.
   *
   * @param string $key
   *   The form key.
   * @param string $value
   *   The form value.
   * @param callable $formatter
   *   The formatter callback.
   */
  public function __construct(string $key, $value, callable $formatter = NULL) {
    $this->key = $key;
    $this->value = $value;
    $this->formatter = $formatter;
  }

  /**
   * Gets the key.
   *
   * @return string
   *   The key.
   */
  public function key() : string {
    return $this->key;
  }

  /**
   * Gets the value.
   *
   * @return mixed
   *   The value.
   */
  public function value() {
    return $this->value;
  }

  /**
   * Gets the formatted value.
   *
   * @return string
   *   The formatted value.
   */
  public function format() : string {
    if (!$this->formatter) {
      if (!is_scalar($this->value)) {
        throw new \LogicException(sprintf('Cannot convert "%s" value to string.', $this->key));
      }
      return (string) $this->value;
    }
    return ($this->formatter)($this->value);
  }

}
