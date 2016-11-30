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
   * @param array $settings
   *   Settings for element.
   *
   * @return $this
   */
  public function set($key, $value, array $settings) {
    $this->values[$key] = new TransactionValue($value, $settings);

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
      'merchant_id' => '',
      'order_number' => '',
      'reference_number' => $this->get('reference_number') ?: $this->setReferenceNumber(''),
      'order_description' => $this->get('order_description') ?: $this->setOrderDescription(''),
      'currency' => '',
      'return_address' => '',
      'cancel_address' => '',
      'notify_address' => '',
      'pending_address' => '',
      'type' => $this->setType($this->getType()),
      'mode' => '',
      'culture' => '',
      'preselected_method' => '',
      'visible_methods' => '',
      'group' => $this->get('group') ?: $this->setGroup(''),
    ];
  }

  /**
   * Getter.
   *
   * @param string $key
   *   The key to get value with.
   *
   * @return \Drupal\commerce_paytrail\Repository\TransactionValue
   *   The transaction value object.
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
    return $this->set('merchant_id', $id, [
      '#weight' => 0,
      '#required' => TRUE,
      '#max_length' => 11,
    ]);
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
    return $this->set('order_number', $order_number, [
      '#weight' => 2,
      '#required' => TRUE,
    ]);
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
    return $this->set('reference_number', $reference_number, [
      '#weight' => 3,
      '#required' => FALSE,
      '#max_length' => 50,
    ]);
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
    return $this->set('order_description', $description, [
      '#weight' => 4,
      '#required' => FALSE,
      '#max_length' => 65000,
    ]);
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
    return $this->set('currency', $currency, [
      '#required' => TRUE,
      '#weight' => 5,
    ]);
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
    return $this->set('return_address', $address, [
      '#required' => TRUE,
      '#weight' => 6,
      '#max_length' => 256,
    ]);
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
    return $this->set('cancel_address', $address, [
      '#weight' => 7,
      '#required' => TRUE,
      '#max_length' => 256,
    ]);
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
    return $this->set('pending_address', $address, [
      '#required' => TRUE,
      '#weight' => 8,
      '#max_length' => 256,
    ]);
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
    return $this->set('notify_address', $address, [
      '#required' => TRUE,
      '#weight' => 9,
      '#max_length' => 256,
    ]);
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
    return $this->set('type', $type, [
      '#required' => TRUE,
      '#weight' => 15,
    ]);
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
    return $this->set('culture', $culture, [
      '#required' => TRUE,
      '#weight' => 16,
    ]);
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
    return $this->set('preselected_method', $method, [
      '#required' => FALSE,
      '#weight' => 17,
    ]);
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
    return $this->set('mode', $mode, [
      '#required' => TRUE,
      '#weight' => 18,
    ]);
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
    return $this->set('visible_methods', implode(',', $methods), [
      '#required' => FALSE,
      '#weight' => 19,
    ]);
  }

  /**
   * Set group. @note This has not been implemented by Paytrail.
   *
   * @param string $group
   *   The group.
   *
   * @return $this
   */
  public function setGroup($group) {
    return $this->set('group', $group, [
      '#required' => FALSE,
      '#weight' => 20,
    ]);
  }

  /**
   * Get values sorted by weight.
   */
  protected function getSortedValues() {
    $values = [];

    foreach ($this->getKeys() as $key => $default_value) {
      // Attempt to use default value.
      if (!$value = $this->get($key)) {
        $value = $default_value;
      }
      if (!$value instanceof TransactionValue) {
        throw new InvalidValueException(sprintf('Invalid data type for %s.', $key));
      }
      $values[$key] = $value;
    }
    uasort($values, function ($a, $b) {
      /** @var TransactionValue $a */
      /** @var TransactionValue $b */
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
   * Get current Paytrail type (E1, S1).
   *
   * @return string
   *   The type.
   */
  abstract protected function getType();

}
