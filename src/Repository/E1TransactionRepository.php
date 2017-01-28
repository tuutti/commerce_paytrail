<?php

namespace Drupal\commerce_paytrail\Repository;

use Drupal\address\AddressInterface;

/**
 * Class S1TransactionRepository.
 *
 * @package Drupal\commerce_paytrail\Repository
 */
class E1TransactionRepository extends TransactionRepository {

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
      'contact_tellno' => [
        '#weight' => 25,
        '#required' => FALSE,
        '#default_value' => '',
      ],
      'contact_cellno' => [
        '#weight' => 26,
        '#required' => FALSE,
        '#default_value' => '',
      ],
      'contact_email' => [
        '#weight' => 27,
        '#required' => TRUE,
      ],
      'contact_firstname' => [
        '#weight' => 28,
        '#required' => TRUE,
      ],
      'contact_lastname' => [
        '#weight' => 29,
        '#required' => TRUE,
      ],
      'contact_company' => [
        '#weight' => 30,
        '#required' => FALSE,
        '#default_value' => '',
      ],
      'contact_addr_street' => [
        '#weight' => 31,
        '#required' => TRUE,
      ],
      'contact_addr_zip' => [
        '#weight' => 32,
        '#required' => TRUE,
      ],
      'contact_addr_city' => [
        '#weight' => 33,
        '#required' => TRUE,
      ],
      'contact_addr_country' => [
        '#weight' => 34,
        '#required' => TRUE,
      ],
      'include_vat' => [
        '#weight' => 35,
        '#required' => TRUE,
      ],
      'items' => [
        '#weight' => 36,
        '#required' => TRUE,
      ],
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
    $this->setContactFirstname($billing_data->getGivenName())
      ->setContactLastname($billing_data->getFamilyName())
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
   * Sets the first name.
   *
   * @param string $firstname
   *   First name.
   *
   * @return $this
   */
  public function setContactFirstname($firstname) {
    return $this->set('contact_firstname', $firstname);
  }

  /**
   * Sets the last name.
   *
   * @param string $lastname
   *   Last name.
   *
   * @return $this
   */
  public function setContactLastname($lastname) {
    return $this->set('contact_lastname', $lastname);
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
   * Sets paytrail product.
   *
   * @param int $delta
   *   The item delta.
   * @param \Drupal\commerce_paytrail\Repository\PaytrailProduct $product
   *   The product.
   *
   * @return $this
   */
  public function setProduct($delta, PaytrailProduct $product) {
    $this->products[$delta] = [
      'item_title' => $product->getTitle(),
      'item_no' => $product->getNumber(),
      'item_amount' => $product->getQuantity(),
      'item_price' => $product->getPrice(),
      'item_tax' => $product->getTax(),
      'item_discount' => $product->getDiscount(),
      'item_type' => $product->getType(),
    ];
    return $this;
  }

  /**
   * Remove product from the products array.
   *
   * @param int $delta
   *   The index.
   *
   * @return $this
   */
  public function removeProduct($delta) {
    if (isset($this->products[$delta])) {
      unset($this->products[$delta]);
    }
    return $this;
  }

  /**
   * Gets the products list.
   *
   * @return array
   *   The list of products.
   */
  public function getProducts() {
    return $this->products;
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $values = parent::build();

    if (count($this->products) != $this->get('items')) {
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
