<?php

declare(strict_types = 1);

namespace Drupal\commerce_paytrail\RequestBuilder;

use Drupal\commerce_paytrail\Header;
use Paytrail\Payment\Configuration;

/**
 * Request builder interface.
 */
interface RequestBuilderInterface {

  /**
   * Gets the default headers.
   *
   * @param string $method
   *   The HTTP method.
   * @param \Paytrail\Payment\Configuration $configuration
   *   The configuration.
   * @param string|null $platformName
   *   The (optional) platform name.
   *
   * @return \Drupal\commerce_paytrail\Header
   *   The header.
   */
  public function createHeaders(
    string $method,
    Configuration $configuration,
    ?string $platformName = NULL,
  ) : Header;

}
