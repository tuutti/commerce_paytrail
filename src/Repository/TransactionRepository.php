<?php

namespace Drupal\commerce_paytrail\Repository;

use Drupal\commerce_paytrail\Exception\InvalidValueException;

/**
 * Class TransactionRepository.
 *
 * @package Drupal\commerce_paytrail\Repository
 */
abstract class TransactionRepository {

  /**
   * The array of values.
   *
   * @var array
   */
  protected $values = [];

  /**
   * Setter.
   *
   * @param string $key
   *   Key.
   * @param mixed $value
   *   Value for key.
   *
   * @return $this
   */
  public function set($key, $value) {
    $this->values[$key] = $value;

    return $this;
  }

  /**
   * Gets the required values.
   *
   * @return array
   *   List of values.
   */
  protected function getKeys() {
    return [
      'merchant_id' => [
        '#weight' => 0,
        '#required' => TRUE,
      ],
      'order_number' => [
        '#weight' => 2,
        '#required' => TRUE,
      ],
      'reference_number' => [
        '#weight' => 3,
        '#required' => FALSE,
        '#default_value' => '',
      ],
      'order_description' => [
        '#weight' => 4,
        '#required' => FALSE,
        '#default_value' => '',
      ],
      'currency' => [
        '#weight' => 5,
        '#required' => TRUE,
        '#default_value' => 'EUR',
      ],
      'return_address' => [
        '#weight' => 6,
        '#required' => TRUE,
      ],
      'cancel_address' => [
        '#weight' => 7,
        '#required' => TRUE,
      ],
      'pending_address' => [
        '#weight' => 8,
        '#required' => TRUE,
      ],
      'notify_address' => [
        '#weight' => 9,
        '#required' => TRUE,
      ],
      'type' => [
        '#weight' => 15,
        '#required' => TRUE,
        '#default_value' => $this->getType(),
      ],
      'mode' => [
        '#weight' => 18,
        '#required' => TRUE,
      ],
      'culture' => [
        '#weight' => 16,
        '#required' => TRUE,
      ],
      'preselected_method' => [
        '#weight' => 17,
        '#required' => FALSE,
        '#default_value' => '',
      ],
      'visible_methods' => [
        '#weight' => 19,
        '#required' => FALSE,
        '#default_value' => '',
      ],
      'group' => [
        '#weight' => 20,
        '#required' => FALSE,
        '#default_value' => '',
      ],
    ];
  }

  /**
   * Getter.
   *
   * @param string $key
   *   The key to get value with.
   *
   * @return \Drupal\commerce_paytrail\Repository\TransactionValue|bool
   *   The transaction value object or false if no value is found.
   */
  protected function get($key) {
    return isset($this->values[$key]) ? $this->values[$key] : FALSE;
  }

  /**
   * Set merchant id.
   *
   * @param string $id
   *   Merchant id.
   *
   * @return $this
   */
  public function setMerchantId($id) {
    return $this->set('merchant_id', $id);
  }

  /**
   * Set order number.
   *
   * @param int $order_number
   *   The order number.
   *
   * @return $this
   */
  public function setOrderNumber($order_number) {
    return $this->set('order_number', $order_number);
  }

  /**
   * Set reference number.
   *
   * @param string $reference_number
   *   The reference number.
   *
   * @return $this
   */
  public function setReferenceNumber($reference_number) {
    return $this->set('reference_number', $reference_number);
  }

  /**
   * Set order description.
   *
   * @param string $description
   *   The order description.
   *
   * @return $this
   */
  public function setOrderDescription($description) {
    return $this->set('order_description', $description);
  }

  /**
   * Set currency.
   *
   * @param string $currency
   *   The currency. EUR is currently only available currency.
   *
   * @return $this
   */
  public function setCurrency($currency = 'EUR') {
    return $this->set('currency', $currency);
  }

  /**
   * Set return address.
   *
   * @param string $address
   *   Return address.
   *
   * @return $this
   */
  public function setReturnAddress($address) {
    return $this->set('return_address', $address);
  }

  /**
   * Set cancel address.
   *
   * @param string $address
   *   Cancel address.
   *
   * @return $this
   */
  public function setCancelAddress($address) {
    return $this->set('cancel_address', $address);
  }

  /**
   * Set pending address.
   *
   * @param string $address
   *   Pending address.
   *
   * @return $this
   */
  public function setPendingAddress($address) {
    return $this->set('pending_address', $address);
  }

  /**
   * Set notify address.
   *
   * @param string $address
   *   Notify address.
   *
   * @return $this
   */
  public function setNotifyAddress($address) {
    return $this->set('notify_address', $address);
  }

  /**
   * Set type.
   *
   * @param string $type
   *   The type.
   *
   * @return $this
   */
  public function setType($type) {
    return $this->set('type', $type);
  }

  /**
   * Set culture.
   *
   * @param string $culture
   *   The culture.
   *
   * @return $this
   */
  public function setCulture($culture) {
    return $this->set('culture', $culture);
  }

  /**
   * Set preselected method.
   *
   * @param int $method
   *   The preselected method id.
   *
   * @return $this
   */
  public function setPreselectedMethod($method) {
    return $this->set('preselected_method', $method);
  }

  /**
   * Set mode.
   *
   * @param string $mode
   *   The mode.
   *
   * @return $this
   */
  public function setMode($mode) {
    return $this->set('mode', $mode);
  }

  /**
   * Set visible methods.
   *
   * @param array $methods
   *   The visible methods.
   *
   * @return $this
   */
  public function setVisibleMethods(array $methods) {
    return $this->set('visible_methods', implode(',', $methods));
  }

  /**
   * Set group. @note This has not been implemented by PaytrailBase.
   *
   * @param string $group
   *   The group.
   *
   * @return $this
   */
  public function setGroup($group) {
    return $this->set('group', $group);
  }

  /**
   * Get values sorted by weight.
   */
  protected function getSortedValues() {
    $values = [];

    foreach ($this->getKeys() as $key => $settings) {
      // Attempt to use default value.
      if (!$value = $this->get($key)) {
        // Empty default value must not be NULL.
        if (!isset($settings['#default_value'])) {
          throw new InvalidValueException(sprintf('No value or default value found for %s.', $key));
        }
        $value = $settings['#default_value'];
      }
      $values[$key] = new TransactionValue($value, $settings);
    }
    uasort($values, function (TransactionValue $a, TransactionValue $b) {
      return $a->weight() > $b->weight() ? 1 : -1;
    });
    return $values;
  }

  /**
   * Build transaction.
   *
   * @return array
   *   List of elements.
   */
  public function build() {
    $values = [];
    $sorted = $this->getSortedValues();

    /** @var TransactionValue $value */
    foreach ($sorted as $key => $value) {
      // Check requirements.
      if (!$value->passRequirements()) {
        throw new InvalidValueException(sprintf('Validation failed for %s.', $key));
      }
      $values[strtoupper($key)] = $value->value();
    }
    return $values;
  }

  /**
   * Get current PaytrailBase type (E1, S1).
   *
   * @return string
   *   The type.
   */
  abstract protected function getType();

}
