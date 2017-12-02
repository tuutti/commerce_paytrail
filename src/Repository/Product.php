<?php

declare(strict_types = 1);

namespace Drupal\commerce_paytrail\Repository;

use Drupal\commerce_order\Entity\OrderItemInterface;
use Drupal\commerce_price\Price;
use Webmozart\Assert\Assert;

/**
 * Provides an object for Paytrail product handling.
 */
class Product {

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
   * The product id.
   *
   * @var string
   */
  protected $id;

  /**
   * Create new self with given order item.
   *
   * @param \Drupal\commerce_order\Entity\OrderItemInterface $item
   *   The order item.
   *
   * @return \Drupal\commerce_paytrail\Repository\Product
   *   The populated instance.
   */
  public static function createFromOrderItem(OrderItemInterface $item) {
    /** @var \Drupal\commerce_paytrail\Repository\Product $object */
    $object = new static();
    $object->setTitle($item->getTitle())
      ->setItemId($item->getPurchasedEntity()->id())
      ->setQuantity($item->getQuantity())
      ->setPrice($item->getTotalPrice());

    return $object;
  }

  /**
   * Builds item form array.
   *
   * @param int $index
   *   The product index.
   *
   * @return array
   *   The build array.
   */
  public function build(int $index) : array {
    $values = [
      sprintf('ITEM_TITLE[%d]', $index) => $this->getTitle(),
      sprintf('ITEM_QUANTITY[%d]', $index) => $this->getQuantity(),
      sprintf('ITEM_UNIT_PRICE[%d]', $index) => $this->getPrice(),
      sprintf('ITEM_VAT_PERCENT[%d]', $index) => $this->getTax(),
      sprintf('ITEM_DISCOUNT_PERCENT[%d]', $index) => $this->getDiscount(),
      sprintf('ITEM_TYPE[%d]', $index) => $this->getType(),
    ];

    if ($this->getItemId()) {
      $values[sprintf('ITEM_ID[%d]', $index)] = $this->getItemId();
    }
    return $values;
  }

  /**
   * Sets the item id.
   *
   * @param string $id
   *   The item id.
   *
   * @return \Drupal\commerce_paytrail\Repository\Product
   *   The self.
   */
  public function setItemId(string $id) : self {
    $this->id = $id;

    return $this;
  }

  /**
   * Sets the product title.
   *
   * @param string $title
   *   The title.
   *
   * @return $this
   */
  public function setTitle($title) : self {
    $this->title = $title;
    return $this;
  }

  /**
   * Gets the product title.
   *
   * @return string
   *   The product title.
   */
  public function getTitle() : string {
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
  public function getQuantity() : int {
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
  public function setPrice(Price $price) : self {
    Assert::oneOf($price->getCurrencyCode(), ['EUR']);

    $this->price = $price;
    return $this;
  }

  /**
   * Gets the product price.
   *
   * @return string
   *   The formatted price.
   */
  public function getPrice() : string {
    return $this->formatPrice((float) $this->price->getNumber());
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
  public function setDiscount(float $discount) {
    $this->discount = $discount;
    return $this;
  }

  /**
   * Gets the discount.
   *
   * @return float
   *   The discount amount.
   */
  public function getDiscount() : float {
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
  public function setProductType($type) : self {
    Assert::oneOf($type, [static::PRODUCT, static::SHIPPING, static::HANDLING]);

    $this->type = $type;
    return $this;
  }

  /**
   * Gets the product type.
   *
   * @return int
   *   The product type.
   */
  public function getType() : int {
    return $this->type;
  }

  /**
   * Gets the product id.
   *
   * @return string
   *   The number.
   */
  public function getItemId() : string {
    return $this->id;
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
  protected function formatPrice(float $price) {
    return number_format($price, 2, '.', '');
  }

}
