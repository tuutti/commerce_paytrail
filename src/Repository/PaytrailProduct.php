<?php

namespace Drupal\commerce_paytrail\Repository;

use Drupal\commerce_order\Entity\OrderItemInterface;
use Drupal\commerce_price\Price;

/**
 * Class PaytrailProduct.
 *
 * @package Drupal\commerce_paytrail\Repository
 */
class PaytrailProduct {

  /**
   * The default product type.
   *
   * @var int
   */
  const PRODUCT = 1;

  /**
   * The shipping product type.
   *
   * @var int
   */
  const SHIPPING = 2;

  /**
   * The item handling product type.
   *
   * @var int
   */
  const HANDLING = 3;

  /**
   * The product title.
   *
   * @var string
   */
  protected $title;

  /**
   * The product quantity.
   *
   * @var int
   */
  protected $quantity;

  /**
   * The price.
   *
   * @var \Drupal\commerce_price\Price
   */
  protected $price;

  /**
   * The tax amount.
   *
   * @var float
   */
  protected $tax = 0.00;

  /**
   * The discount amount.
   *
   * @var int
   */
  protected $discount = 0;

  /**
   * The product type.
   *
   * @var int
   */
  protected $type = self::PRODUCT;

  /**
   * The product number.
   *
   * @var string
   */
  protected $number = '';

  /**
   * Product constructor.
   *
   * @param array $settings
   *   Values to populate.
   */
  public function __construct(array $settings = []) {
    foreach ($settings as $key => $value) {
      if (!property_exists($this, $key)) {
        continue;
      }
      $this->{$key} = $value;
    }
  }

  /**
   * Create new self with given order item.
   *
   * @param \Drupal\commerce_order\Entity\OrderItemInterface $item
   *   The order item.
   *
   * @return \Drupal\commerce_paytrail\Repository\PaytrailProduct
   *   The populated instance.
   */
  public static function createWithOrderItem(OrderItemInterface $item) {
    /** @var \Drupal\commerce_paytrail\Repository\PaytrailProduct $object */
    $object = new static();
    $object->setTitle($item->getTitle())
      ->setQuantity($item->getQuantity())
      ->setPrice($item->getTotalPrice());

    return $object;
  }

  /**
   * Sets the product title.
   *
   * @param string $title
   *   The title.
   *
   * @return $this
   */
  public function setTitle($title) {
    $this->title = $title;
    return $this;
  }

  /**
   * Gets the product title.
   *
   * @return string
   *   The product title.
   */
  public function getTitle() {
    return $this->title;
  }

  /**
   * Sets the product quantity.
   *
   * @param int $quantity
   *   The quantity.
   *
   * @return $this
   */
  public function setQuantity($quantity) {
    $this->quantity = $quantity;
    return $this;
  }

  /**
   * Gets the product quantity.
   *
   * @return int
   *   The product quantity.
   */
  public function getQuantity() {
    return (int) round($this->quantity);
  }

  /**
   * Sets the product price.
   *
   * @param \Drupal\commerce_price\Price $price
   *   The price.
   *
   * @return $this
   */
  public function setPrice(Price $price) {
    $this->price = $price;
    return $this;
  }

  /**
   * Gets the product price.
   *
   * @return string
   *   The formatted price.
   */
  public function getPrice() {
    return $this->formatPrice($this->price->getNumber());
  }

  /**
   * Sets the product tax.
   *
   * @param float $tax
   *   The tax.
   *
   * @return $this
   */
  public function setTax($tax) {
    $this->tax = $tax;
    return $this;
  }

  /**
   * Gets the formatted tax.
   *
   * @return string
   *   The formatted tax.
   */
  public function getTax() {
    return $this->formatPrice($this->tax);
  }

  /**
   * Sets the discount.
   *
   * @param float $discount
   *   The discount.
   *
   * @return $this
   */
  public function setDiscount($discount) {
    $this->discount = $discount;
    return $this;
  }

  /**
   * Gets the discount.
   *
   * @return float
   *   The discount amount.
   */
  public function getDiscount() {
    return $this->discount;
  }

  /**
   * Sets the product type.
   *
   * @param int $type
   *   The product type.
   *
   * @return $this
   */
  public function setProductType($type) {
    $this->type = $type;
    return $this;
  }

  /**
   * Gets the product type.
   *
   * @return int
   *   The product type.
   */
  public function getType() {
    return $this->type;
  }

  /**
   * Sets the product number.
   *
   * @param string $number
   *   The number.
   *
   * @return $this
   */
  public function setNumber($number) {
    $this->number = $number;
    return $this;
  }

  /**
   * Gets the product number.
   *
   * @return string
   *   The number.
   */
  public function getNumber() {
    return $this->number;
  }

  /**
   * Formats the price.
   *
   * @param float $price
   *   The price.
   *
   * @return string
   *   Formatted price component.
   */
  protected function formatPrice($price) {
    return number_format($price, 2, '.', '');
  }

}
