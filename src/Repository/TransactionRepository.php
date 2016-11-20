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

  /**
   * Ignore value is used to ignore values from mac calculation.
   *
   * @var string
   */
  const IGNORE_VALUE = -1;

  const ITEM_PRODUCT = 1;
  const ITEM_SHIPPING = 2;
  const ITEM_HANDING = 3;


  /**
   * Correct order for every possible value.
   *
   * @var array
   */
  protected $values = [
    'MERCHANT_ID' => '',
    'AMOUNT' => self::IGNORE_VALUE,
    'ORDER_NUMBER' => '',
    'REFERENCE_NUMBER' => '',
    'ORDER_DESCRIPTION' => '',
    'CURRENCY' => 'EUR',
    'RETURN_ADDRESS' => '',
    'CANCEL_ADDRESS' => '',
    'PENDING_ADDRESS' => '',
    'NOTIFY_ADDRESS' => '',
    'TYPE' => '',
    'CULTURE' => '',
    'PRESELECTED_METHOD' => '',
    'MODE' => '',
    'VISIBLE_METHODS' => [],
    'GROUP' => '',
    'CONTACT_TELLNO' => self::IGNORE_VALUE,
    'CONTACT_CELLNO' => self::IGNORE_VALUE,
    'CONTACT_EMAIL' => self::IGNORE_VALUE,
    'CONTACT_FIRSTNAME' => self::IGNORE_VALUE,
    'CONTACT_LASTNAME' => self::IGNORE_VALUE,
    'CONTACT_COMPANY' => self::IGNORE_VALUE,
    'CONTACT_ADDR_STREET' => self::IGNORE_VALUE,
    'CONTACT_ADDR_ZIP' => self::IGNORE_VALUE,
    'CONTACT_ADDR_CITY' => self::IGNORE_VALUE,
    'CONTACT_ADDR_COUNTRY' => self::IGNORE_VALUE,
    'INCLUDE_VAT' => self::IGNORE_VALUE,
    'ITEMS' => self::IGNORE_VALUE,
  ];

  protected $products = [];

  /**
   * TransactionRepository constructor.
   *
   * @param array $values
   *   List of values to store.
   */
  public function __construct(array $values = []) {
    foreach ($values as $key => $value) {
      if (!isset($this->values[$key])) {
        continue;
      }
      $this->set($key, $value);
    }
  }

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
   * Set merchant id.
   *
   * @param string $id
   *   Merchant id.
   *
   * @return $this
   */
  public function setMerchantId($id) {
    return $this->set('MERCHANT_ID', $id);
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

    return $this->set('AMOUNT', $formatted);
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
    return $this->set('ORDER_NUMBER', $order_number);
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
    return $this->set('REFERENCE_NUMBER', $reference_number);
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
    return $this->set('ORDER_DESCRIPTION', $description);
  }

  /**
   * Set currency.
   *
   * @param string $currency
   *   The currency. EUR is currently only available currency.
   *
   * @return $this
   */
  public function setCurrency($currency) {
    return $this->set('CURRENCY', $currency);
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
    return $this->set(strtoupper($type) . '_ADDRESS', $address);
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
    return $this->set('TYPE', $type);
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
    return $this->set('CULTURE', $culture);
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
    return $this->set('PRESELECTED_METHOD', $method);
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
    return $this->set('MODE', $mode);
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
    return $this->set('VISIBLE_METHODS', implode(',', $methods));
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
    return $this->set('GROUP', $group);
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
    return $this->set('CONTACT_TELLNO', $number);
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
    return $this->set('CONTACT_CELLNO', $number);
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
    return $this->set('CONTACT_EMAIL', $email);
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

    $this->set('CONTACT_FIRSTNAME', $firstname)
      ->set('CONTACT_LASTNAME', $lastname);

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
    return $this->set('CONTACT_COMPANY', $company);
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
    return $this->set('CONTACT_ADDR_STREET', $address);
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
    return $this->set('CONTACT_ADDR_ZIP', $zip);
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
    return $this->set('CONTACT_ADDR_CITY', $city);
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
    return $this->set('CONTACT_ADDR_COUNTRY', $country);
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
    return $this->set('INCLUDE_VAT', $status);
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
    return $this->set('ITEMS', (int) $items);
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
    $this->products[] = [
      'ITEM_TITLE' => $item->getTitle(),
      'ITEM_NO' => $number,
      'ITEM_AMOUNT' => round($item->getQuantity()),
      'ITEM_PRICE' => number_format($item->getTotalPrice()->getNumber(), 2, '.', ''),
      'ITEM_TAX' => $tax_percent,
      'ITEM_DISCOUNT' => $discount,
      'ITEM_TYPE' => $type,
    ];
    return $this;
  }

  /**
   * Build transaction.
   *
   * @return array
   *   List of elements.
   */
  public function build() {
    $values = [];
    foreach ($this->values as $key => $value) {
      // Ignore certain values.
      if ($value === static::IGNORE_VALUE) {
        continue;
      }
      $values[$key] = $value;
    }
    // Build products list.
    foreach ($this->products as $delta => $product) {
      foreach ($product as $key => $value) {
        $values[sprintf('%s[%d]', $key, $delta)] = $value;
      }
    }
    return $values;
  }

}
