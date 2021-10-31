<?php

declare(strict_types = 1);

namespace Drupal\commerce_paytrail\Repository\Product;

use Drupal\commerce_paytrail\AssertTrait;
use Drupal\commerce_paytrail\Repository\FormTrait;
use Drupal\commerce_paytrail\Repository\FormValueCallbackTrait;
use Drupal\commerce_price\Price;
use Webmozart\Assert\Assert;

/**
 * Provides an object for Paytrail product handling.
 */
abstract class ProductBase {

  use AssertTrait;
  use FormTrait;
  use FormValueCallbackTrait;

  /**
   * {@inheritdoc}
   */
  public function build() : array {
    $values = [
      'ITEM_TYPE' => $this->getType(),
    ];

    foreach ($this->values as $key => $value) {
      $values[$key] = $value->format();
    }
    return $values;
  }

  /**
   * Sets the item id.
   *
   * @param string $id
   *   The item id.
   *
   * @return $this
   *   The self.
   */
  public function setItemId(string $id) : self {
    Assert::numeric($id);
    Assert::maxLength($id, 16);

    $this->setValue('ITEM_ID', $id);

    return $this;
  }

  /**
   * Gets the product id.
   *
   * @return string
   *   The number.
   */
  public function getItemId() : string {
    return $this->getValue('ITEM_ID');
  }

  /**
   * Sets the product title.
   *
   * @param string $title
   *   The title.
   *
   * @return $this
   *   The self.
   */
  public function setTitle(string $title) : self {
    $this->setValue('ITEM_TITLE', $title, function (string $string) {
      return $this->sanitizeText($string);
    });
    return $this;
  }

  /**
   * Gets the product title.
   *
   * @return string
   *   The product title.
   */
  public function getTitle() : string {
    return $this->getValue('ITEM_TITLE');
  }

  /**
   * Sets the product quantity.
   *
   * @param int $quantity
   *   The quantity.
   *
   * @return $this
   *   The self.
   */
  public function setQuantity(int $quantity) : self {
    $this->setValue('ITEM_QUANTITY', $quantity);
    return $this;
  }

  /**
   * Gets the product quantity.
   *
   * @return int
   *   The product quantity.
   */
  public function getQuantity() : int {
    return $this->getValue('ITEM_QUANTITY');
  }

  /**
   * Sets the product price.
   *
   * @param \Drupal\commerce_price\Price $price
   *   The price.
   *
   * @return $this
   *   The self.
   */
  public function setPrice(Price $price) : self {
    $this->assertAmountBetween($price, '0', '499999');

    $this->setValue('ITEM_UNIT_PRICE', $price, $this->formatPrice());
    return $this;
  }

  /**
   * Gets the product price.
   *
   * @return string
   *   The formatted price.
   */
  public function getPrice() : string {
    return $this->getValue('ITEM_UNIT_PRICE');
  }

  /**
   * Sets the product tax.
   *
   * @param float $tax
   *   The tax.
   *
   * @return $this
   *   The self.
   */
  public function setTax(float $tax) : self {
    $this->assertBetween($tax, 0, 100);

    $this->setValue('ITEM_VAT_PERCENT', $tax);
    return $this;
  }

  /**
   * Gets the formatted tax.
   *
   * @return string
   *   The tax percent.
   */
  public function getTax() : string {
    return (string) $this->getValue('ITEM_VAT_PERCENT');
  }

  /**
   * Sets the discount.
   *
   * @param float $discount
   *   The discount percent.
   *
   * @return $this
   *   The self.
   */
  public function setDiscount(float $discount) : self {
    $this->assertBetween($discount, 0, 100);

    $this->setValue('ITEM_DISCOUNT_PERCENT', $discount);
    return $this;
  }

  /**
   * Gets the discount.
   *
   * @return float
   *   The discount amount.
   */
  public function getDiscount() : float {
    return $this->getValue('ITEM_DISCOUNT_PERCENT');
  }

  /**
   * Gets the product type.
   *
   * @return int
   *   The product type.
   */
  abstract public function getType() : int;

}
