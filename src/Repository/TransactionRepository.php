<?php

namespace Drupal\commerce_paytrail\Repository;

use Drupal\commerce_order\Entity\OrderItemInterface;
use Drupal\commerce_price\Price;

/**
 * Class TransactionRepository.
 *
 * @package Drupal\commerce_paytrail\Repository
 */
class TransactionRepository {

  const ITEM_PRODUCT = 1;
  const ITEM_SHIPPING = 2;
  const ITEM_HANDING = 3;

  /**
   * Array of values to build transaction array.
   *
   * @var array
   */
  protected $values = [];

  /**
   * Array of products.
   *
   * @var array
   */
  protected $products = [];

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
   * Gets the raw value.
   *
   * @param string $key
   *   The key to get value with.
   *
   * @return mixed
   *   The value if found, NULL if not.
   */
  public function raw($key) {
    return isset($this->values[$key]) ? $this->values[$key] : NULL;
  }

  /**
   * Getter.
   *
   * @param string $key
   *   The key to get value with.
   * @param string $type
   *   The Paytrail type.
   * @param bool $required
   *   Indicates if the field is required to have a value.
   *
   * @return \Drupal\commerce_paytrail\Repository\TransactionValue
   *   The transaction value object.
   */
  protected function get($key, $type = NULL, $required = TRUE) {
    return new TransactionValue($this->raw($key), $required, $type);
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
   * Set amount.
   *
   * @param \Drupal\commerce_price\Price $price
   *   The amount.
   *
   * @return $this
   */
  public function setAmount(Price $price) {
    $formatted = number_format($price->getNumber(), 2, '.', '');

    return $this->set('amount', $formatted);
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
   * @param string $type
   *   The return address type.
   * @param string $address
   *   Return address.
   *
   * @return $this
   */
  public function setReturnAddress($type, $address) {
    return $this->set($type . '_address', $address);
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
   * Set group. @note This has not been implemented by Paytrail.
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
   * Set contact telephone number.
   *
   * @param string $number
   *   Telephone number.
   *
   * @return $this
   */
  public function setContactTelno($number) {
    return $this->set('contact_tellno', $number);
  }

  /**
   * Set contact cell number.
   *
   * @param string $number
   *   Telephone number.
   *
   * @return $this
   */
  public function setContactCellno($number) {
    return $this->set('contact_cellno', $number);
  }

  /**
   * Set contact email.
   *
   * @param string $email
   *   Contact email.
   *
   * @return $this
   */
  public function setContactEmail($email) {
    return $this->set('contact_email', $email);
  }

  /**
   * Set fist / lastname.
   *
   * @param string $full_name
   *   Full name.
   *
   * @return $this
   */
  public function setContactName($full_name) {
    $names = explode(' ', $full_name);

    // Lastname is required field by Paytrail, but not by billing profile.
    // Fallback to double first names.
    if (empty($names[1])) {
      $names[1] = reset($names);
    }
    list($firstname, $lastname) = $names;

    $this->set('contact_firstname', $firstname)
      ->set('contact_lastname', $lastname);

    return $this;
  }

  /**
   * Set company.
   *
   * @param string $company
   *   Set company.
   *
   * @return $this
   */
  public function setContactCompany($company) {
    return $this->set('contact_company', $company);
  }

  /**
   * Set contact address.
   *
   * @param string $address
   *   Set contact address.
   *
   * @return $this
   */
  public function setContactAddress($address) {
    return $this->set('contact_addr_street', $address);
  }

  /**
   * Contact zip number.
   *
   * @param string $zip
   *   Zip number.
   *
   * @return $this
   */
  public function setContactZip($zip) {
    return $this->set('contact_addr_zip', $zip);
  }

  /**
   * Set city.
   *
   * @param string $city
   *   Set contact city.
   *
   * @return $this
   */
  public function setContactCity($city) {
    return $this->set('contact_addr_city', $city);
  }

  /**
   * Set countrycode.
   *
   * @param string $country
   *   The country code.
   *
   * @return $this
   */
  public function setContactCountry($country) {
    return $this->set('contact_addr_country', $country);
  }

  /**
   * Set include vat.
   *
   * @param bool $status
   *   Boolean indigating status.
   *
   * @return $this
   */
  public function setIncludeVat($status) {
    return $this->set('include_vat', $status);
  }

  /**
   * Set items count.
   *
   * @param int $items
   *   Number of items included in order.
   *
   * @return $this
   */
  public function setItems($items) {
    return $this->set('items', (int) $items);
  }

  /**
   * Set product.
   *
   * @param \Drupal\commerce_order\Entity\OrderItemInterface $item
   *   The order item object.
   * @param int $discount
   *   Discount amount.
   * @param int $type
   *   Product type.
   * @param string $number
   *   Product number.
   * @param float $tax_percent
   *   Tax percentage.
   *
   * @return $this
   */
  public function setProduct(OrderItemInterface $item, $discount = 0, $type = self::ITEM_PRODUCT, $number = '', $tax_percent = 0.00) {
    $item_price = $item->getTotalPrice()->getNumber();

    $tax = $tax_percent;
    // Calculate tax portion of the price.
    if ($tax_percent > 0) {
      $tax = $item_price * $tax_percent;
    }
    $this->products[] = [
      'item_title' => $item->getTitle(),
      'item_no' => $number,
      'item_amount' => round($item->getQuantity()),
      'item_price' => number_format($item_price, 2, '.', ''),
      'item_tax' => $tax,
      'item_discount' => $discount,
      'item_type' => $type,
    ];
    return $this;
  }

  /**
   * Hash calculation requires specific order.
   *
   * @return array
   *   Array of TransactionValue objects.
   */
  protected function getBuildOrder() {
    $build_order = [
      'merchant_id' => $this->get('merchant_id'),
      'amount' => $this->get('amount', 'S1'),
      'order_number' => $this->get('order_number'),
      'reference_number' => $this->get('reference_number', NULL, FALSE),
      'order_description' => $this->get('order_description', NULL, FALSE),
      'currency' => $this->get('currency'),
      'return_address' => $this->get('return_address'),
      'cancel_address' => $this->get('cancel_address'),
      'pending_address' => $this->get('pending_address'),
      'notify_address' => $this->get('notify_address'),
      'type' => $this->get('type'),
      'culture' => $this->get('culture'),
      'preselected_method' => $this->get('preselected_method', NULL, FALSE),
      'mode' => $this->get('mode'),
      'visible_methods' => $this->get('visible_methods', NULL, FALSE),
      'group' => $this->get('group', NULL, FALSE),
      'contact_tellno' => $this->get('contact_tellno', 'E1', FALSE),
      'contact_cellno' => $this->get('contact_cellno', 'E1', FALSE),
      'contact_email' => $this->get('contact_email', 'E1'),
      'contact_firstname' => $this->get('contact_firstname', 'E1'),
      'contact_lastname' => $this->get('contact_lastname', 'E1'),
      'contact_company' => $this->get('contact_company', 'E1', FALSE),
      'contact_addr_street' => $this->get('contact_addr_street', 'E1'),
      'contact_addr_zip' => $this->get('contact_addr_zip', 'E1'),
      'contact_addr_city' => $this->get('contact_addr_city', 'E1'),
      'contact_addr_country' => $this->get('contact_addr_country', 'E1'),
      'include_vat' => $this->get('include_vat', 'E1'),
      'items' => $this->get('items', 'E1', FALSE),
    ];
    return $build_order;
  }

  /**
   * Build transaction.
   *
   * @return array
   *   List of elements.
   */
  public function build() {
    $values = [];
    /** @var TransactionValue $value */
    foreach ($this->getBuildOrder() as $key => $value) {
      // Skip types not required by Paytrail type.
      if (!$value->matches($this->raw('type'))) {
        continue;
      }
      // Check requirements.
      if (!$value->passRequirements()) {
        throw new \InvalidArgumentException(sprintf('%s is marked as required and is missing a value.', $key));
      }
      $values[strtoupper($key)] = $value->value();
    }
    if ($this->get('type') === 'S1') {
      return $values;
    }
    if (count($this->products) != $this->raw('items')) {
      throw new \InvalidArgumentException('Given item count does not match the actual product count.');
    }
    // Build products list.
    foreach ($this->products as $delta => $product) {
      foreach ($product as $key => $value) {
        $values[sprintf('%s[%d]', strtoupper($key), $delta)] = $value;
      }
    }
    return $values;
  }

}
