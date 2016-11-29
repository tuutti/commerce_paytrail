<?php

namespace Drupal\commerce_paytrail\Repository;

use Drupal\address\AddressInterface;
use Drupal\commerce_order\Entity\OrderItemInterface;

/**
 * Class SimpleTransactionRepository.
 *
 * @package Drupal\commerce_paytrail\Repository
 */
class EnterpriseTransactionRepository extends TransactionRepository {

  const ITEM_PRODUCT = 1;
  const ITEM_SHIPPING = 2;
  const ITEM_HANDING = 3;

  /**
   * The list of products.
   *
   * @var array
   */
  private $products;

  /**
   * {@inheritdoc}
   */
  protected function getKeys() {
    $values = [
      'contact_tellno' => $this->get('contact_tellno') ?: $this->setContactTelno(''),
      'contact_cellno' => $this->get('contact_cellno') ?: $this->setContactCellno(''),
      'contact_email' => '',
      'contact_firstname' => '',
      'contact_lastname' => '',
      'contact_company' => '',
      'contact_addr_street' => '',
      'contact_addr_zip' => '',
      'contact_addr_city' => '',
      'contact_addr_country' => '',
      'include_vat' => '',
      'items' => '',
    ];
    return $values + parent::getKeys();
  }

  /**
   * Populate all contact detail with the billing profile.
   *
   * @param \Drupal\address\AddressInterface $billing_data
   *   The billing profile.
   *
   * @return $this
   */
  public function setBillingProfile(AddressInterface $billing_data) {
    $this->setContactName($billing_data->getGivenName())
      ->setContactCompany($billing_data->getOrganization())
      ->setContactAddress($billing_data->getAddressLine1())
      ->setContactZip($billing_data->getPostalCode())
      ->setContactCity($billing_data->getLocality())
      ->setContactCountry($billing_data->getCountryCode());

    return $this;
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
    return $this->set('contact_tellno', $number, [
      '#required' => FALSE,
      '#weight' => 25,
      '#max_length' => 64,
    ]);
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
    return $this->set('contact_cellno', $number, [
      '#required' => FALSE,
      '#weight' => 26,
      '#max_length' => 64,
    ]);
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
    return $this->set('contact_email', $email, [
      '#required' => TRUE,
      '#weight' => 27,
      '#max_length' => 255,
    ]);
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

    $this->set('contact_firstname', $firstname, [
      '#required' => TRUE,
      '#weight' => 28,
      '#max_length' => 64,
    ])
      ->set('contact_lastname', $lastname, [
        '#required' => TRUE,
        '#weight' => 29,
        '#max_length' => 64,
      ]);

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
    return $this->set('contact_company', $company, [
      '#required' => FALSE,
      '#weight' => 30,
      '#max_length' => 128,
    ]);
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
    return $this->set('contact_addr_street', $address, [
      '#required' => TRUE,
      '#weight' => 31,
      '#max_length' => 128,
    ]);
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
    return $this->set('contact_addr_zip', $zip, [
      '#required' => TRUE,
      '#weight' => 32,
      '#max_length' => 16,
    ]);
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
    return $this->set('contact_addr_city', $city, [
      '#required' => TRUE,
      '#weight' => 33,
      '#max_length' => 64,
    ]);
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
    return $this->set('contact_addr_country', $country, [
      '#required' => TRUE,
      '#weight' => 34,
    ]);
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
    return $this->set('include_vat', $status, [
      '#required' => TRUE,
      '#weight' => 35,
    ]);
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
    return $this->set('items', (int) $items, [
      '#weight' => 36,
      '#required' => TRUE,
    ]);
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
      'item_amount' => (int) round($item->getQuantity()),
      'item_price' => number_format($item_price, 2, '.', ''),
      'item_tax' => number_format($tax, 2, '.', ''),
      'item_discount' => $discount,
      'item_type' => $type,
    ];
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $values = parent::build();

    if (count($this->products) != $this->get('items')->value()) {
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

  /**
   * {@inheritdoc}
   */
  protected function getType() {
    return 'E1';
  }

}
