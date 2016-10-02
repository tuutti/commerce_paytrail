<?php

namespace Drupal\commerce_paytrail\Repository;

/**
 * Class Method.
 *
 * @package Drupal\commerce_paytrail\Repository
 */
class Method {

  /**
   * Payment method label.
   *
   * @var string
   */
  protected $label;

  /**
   * Remote payment method id.
   *
   * @var int
   */
  protected $id;

  /**
   * Display label.
   *
   * @var string
   */
  protected $displayLabel;

  /**
   * Method constructor.
   *
   * @param int $id
   *   Payment method id.
   * @param string $label
   *   Payment method label.
   * @param string $display_label
   *   Display label.
   */
  public function __construct($id = NULL, $label = NULL, $display_label = NULL) {
    $this->setId($id)
      ->setLabel($label)
      // Fallback to administrative label.
      ->setDisplayLabel($display_label ?: $label);
  }

  /**
   * Set display label.
   *
   * @param string $label
   *   Display label.
   *
   * @return $this
   */
  public function setDisplayLabel($label) {
    $this->displayLabel = $label;
    return $this;
  }

  /**
   * Payment method label.
   *
   * @param string $label
   *   Payment method label.
   *
   * @return $this
   */
  public function setLabel($label) {
    $this->label = $label;
    return $this;
  }

  /**
   * Set remote payment method id.
   *
   * @param int $id
   *   Method id.
   *
   * @return $this
   */
  public function setId($id) {
    $this->id = $id;
    return $this;
  }

  /**
   * Get remote payment method id.
   *
   * @return mixed
   *   Remote method id.
   */
  public function getId() {
    return $this->id;
  }

  /**
   * Get payment method label.
   *
   * @return mixed
   *   Label.
   */
  public function getLabel() {
    return $this->label;
  }

  /**
   * Display label.
   *
   * @return string
   *   Display label.
   */
  public function getDisplayLabel() {
    return $this->displayLabel;
  }

}
