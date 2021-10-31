<?php

declare(strict_types = 1);

namespace Drupal\commerce_paytrail\Repository;

use Drupal\commerce_paytrail\SanitizeTrait;
use Drupal\commerce_price\Calculator;
use Drupal\commerce_price\Price;

/**
 * Provides default formatters for form manager.
 */
trait FormValueCallbackTrait {

  use SanitizeTrait;

  /**
   * The format price formatter.
   *
   * Rounds the price to 2 decimals.
   *
   * @return \Closure
   *   The callback.
   */
  protected function formatPrice() : \Closure {
    return function (Price $price) {
      $rounded = Calculator::round($price->getNumber(), 2);
      [$value, $digits] = array_pad(explode('.', $rounded), 2, '');
      // Make sure we always have 2 decimals.
      $padding = str_repeat('0', 2 - strlen($digits));

      return "$value.$digits$padding";
    };
  }

  /**
   * The sanitize strict formatter.
   *
   * Sanitizes text through 'strict' filter.
   *
   * @return \Closure
   *   The callback.
   */
  protected function sanitizeStrictCallback() : \Closure {
    return function (string $string) : string {
      return $this->sanitizeTextStrict($string);
    };
  }

  /**
   * The sanitize formatter.
   *
   * Sanitizes text through 'default' filter.
   *
   * @return \Closure
   *   The callback.
   */
  protected function sanitizeCallback() : \Closure {
    return function (string $string) : string {
      return $this->sanitizeText($string);
    };
  }

}

