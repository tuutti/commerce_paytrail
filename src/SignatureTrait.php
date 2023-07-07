<?php

declare(strict_types = 1);

namespace Drupal\commerce_paytrail;

use Drupal\commerce_paytrail\Exception\SecurityHashMismatchException;
use Paytrail\SDK\Exception\HmacException;
use Paytrail\SDK\Util\Signature;

/**
 * A trait to create and validate Paytrail signatures.
 */
trait SignatureTrait {

  /**
   * Validates the response.
   *
   * @param string $secret
   *   The secret.
   * @param array $headers
   *   The headers.
   * @param string $body
   *   The body.
   *
   * @return $this
   *   The self.
   *
   * @throws \Drupal\commerce_paytrail\Exception\SecurityHashMismatchException
   */
  public function validateSignature(string $secret, array $headers, string $body = '') : self {
    if (empty($secret) || !isset($headers['signature'])) {
      throw new SecurityHashMismatchException('Signature missing.');
    }

    try {
      Signature::validateHmac($headers, $body, $headers['signature'], $secret);
    }
    catch (HmacException $e) {
      throw new SecurityHashMismatchException('Signature does not match.', previous: $e);
    }
    return $this;
  }

}
