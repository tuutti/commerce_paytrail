<?php

namespace Drupal\commerce_paytrail\Repository;

/**
 * Class TransactionValue.
 *
 * @package Drupal\commerce_paytrail\Repository
 */
final class TransactionValue {

  /**
   * The value.
   *
   * @var mixed
   */
  protected $value;

  /**
   * Indicates whether item is required to have value.
   *
   * @var bool
   */
  protected $required;

  /**
   * The required Paytrail type (S1, E1).
   *
   * @var string
   */
  protected $type;

  /**
   * TransactionValue constructor.
   *
   * @param mixed $value
   *   The value.
   * @param bool $required
   *   Whether item is required to have value.
   * @param string $type
   *   The required type (S1, E1 or NULL for both).
   */
  public function __construct($value, $required, $type) {
    $this->value = $value;
    $this->required = (bool) $required;
    $this->type = $type;
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
   * Whether the value passes requirements.
   */
  public function passRequirements() {
    if ($this->required) {
      return !empty($this->value);
    }
    return TRUE;
  }

  /**
   * Check if given type matches.
   *
   * @param string $type
   *   The type to compare against.
   *
   * @return bool
   *   Whether type matches.
   */
  public function matches($type) {
    if (is_null($this->type)) {
      return TRUE;
    }
    return $type == $this->type;
  }

}
