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
   * The settings array.
   *
   * @var array
   */
  protected $settings;

  /**
   * TransactionValue constructor.
   *
   * @param mixed $value
   *   The value.
   * @param array $settings
   *   The item settings.
   */
  public function __construct($value, array $settings = []) {
    $this->value = $value;
    $this->settings = $settings;
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
   * Gets the given setting.
   *
   * @param string $key
   *   The setting key.
   *
   * @return mixed|null
   *   NULL if given setting not found.
   */
  protected function getSetting($key) {
    return isset($this->settings[$key]) ? $this->settings[$key] : NULL;
  }

  /**
   * Get elements weight.
   *
   * @return int
   *   The weight.
   */
  public function weight() {
    return $this->getSetting('#weight') ?: 0;
  }

  /**
   * Whether the value passes requirements.
   */
  public function passRequirements() {
    if ($this->getSetting('#required') && empty($this->value)) {
      return FALSE;
    }
    if (($length = $this->getSetting('#max_length')) && mb_strlen($this->value()) > $length) {
      return FALSE;
    }
    return TRUE;
  }

}
