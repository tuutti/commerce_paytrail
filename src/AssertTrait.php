<?php

declare(strict_types = 1);

namespace Drupal\commerce_paytrail;

use Drupal\commerce_price\Price;
use Webmozart\Assert\Assert;

/**
 * Provides validation functionality.
 */
trait AssertTrait {

  /**
   * Asserts that the url is valid.
   *
   * @param string $url
   *   The url.
   */
  public function assertValidUrl(string $url) : void {
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
      throw new \InvalidArgumentException(sprintf('"%s" is not a valid URL.', $url));
    }
  }

  /**
   * Asserts that the value is between given values.
   *
   * @param float $value
   *   The value.
   * @param float $min
   *   The min.
   * @param float $max
   *   The max.
   */
  public function assertBetween(float $value, float $min, float $max) : void {
    if ($value < $min || $value > $max) {
      throw new \InvalidArgumentException(sprintf('Value must be between %s and %s', $min, $max));
    }
  }

  /**
   * Asserts that the price is between given values.
   *
   * @param \Drupal\commerce_price\Price $price
   *   The price.
   * @param float $min
   *   The minimum price.
   * @param float $max
   *   The maximum price.
   */
  public function assertAmountBetween(Price $price, float $min, float $max) : void {
    $this->assertBetween((float) $price->getNumber(), $min, $max);
  }

  /**
   * Asserts that the text matches the regex.
   *
   * @param string $text
   *   The text.
   *
   * @note Regex taken from Paytrail documentation.
   * @see http://docs.paytrail.com/en/index-all.html#payment-api.e2
   */
  public function assertText(string $text) : void {
    Assert::regex($text, '/^[\pL-0-9- "\', ()\[\]{}*+\-_,.]*$/u');
  }

  /**
   * Asserts that the value is phone number.
   *
   * @param string $phone
   *   The phone number.
   *
   * @note Regex taken from Paytrail documentation.
   * @see http://docs.paytrail.com/en/index-all.html#payment-api.e2
   */
  public function assertPhone(string $phone) : void {
    Assert::maxLength($phone, 64);
    Assert::regex($phone, '/^[ 0-9+-]*$/s');
  }

  /**
   * Asserts that the value is postal code.
   *
   * @param string $code
   *   The postal code.
   *
   * @note Regex taken from Paytrail documentation.
   * @see http://docs.paytrail.com/en/index-all.html#payment-api.e2
   */
  public function assertPostalCode(string $code) : void {
    Assert::maxLength($code, 16);
    Assert::regex($code, '/^[0-9a-zA-Z]*$/');
  }

  /**
   * Asserts that the string matches the regex.
   *
   * @param string $string
   *   The text.
   *
   * @note Regex taken from Paytrail documentation.
   * @see http://docs.paytrail.com/en/index-all.html#payment-api.e2
   */
  public function assertNonStrictText(string $string) : void {
    Assert::regex($string, '/^[\pL-0-9- "\', ()\[\]{}*\/ + \-_,.:&!?@#$£=*;~]*$/u');
  }

}

