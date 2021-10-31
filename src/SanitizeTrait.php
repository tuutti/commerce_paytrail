<?php

declare(strict_types = 1);

namespace Drupal\commerce_paytrail;

use Drupal\commerce_paytrail\Repository\RegularExpressions;

/**
 * Trait to filter invalid characters.
 */
trait SanitizeTrait {

  /**
   * Sanitizes the given string.
   *
   * Paytrail doesn't allow characters such as €, <, > or % so
   * we need to strip those from certain fields.
   *
   * @param string $string
   *   The string.
   * @param string $regex
   *   The regex.
   *
   * @return string
   *   The string.
   */
  public function sanitize(string $string, string $regex = 'default') : string {
    $regexes = [
      'default' => RegularExpressions::SANITIZE_TEXT_DEFAULT,
      'strict' => RegularExpressions::SANITIZE_TEXT_STRICT,
    ];
    $regex = $regexes[$regex] ?? $regexes['default'];

    return preg_replace($regex, '', $string);
  }

  /**
   * Sanitizes the given string.
   *
   * @param string $string
   *   The string to sanitize.
   *
   * @return string
   *   The sanitized string.
   */
  public function sanitizeText(string $string) : string {
    return $this->sanitize($string, 'default');
  }

  /**
   * Sanitizes the given string.
   *
   * Uses strict regexp.
   *
   * @param string $string
   *   The string to sanitize.
   *
   * @return string
   *   The sanitized string.
   */
  public function sanitizeTextStrict(string $string) : string {
    return $this->sanitize($string, 'strict');
  }

}
