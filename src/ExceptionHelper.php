<?php

declare(strict_types = 1);

namespace Drupal\commerce_paytrail;

use Drupal\commerce_payment\Exception\PaymentGatewayException;
use Paytrail\Payment\ApiException;

/**
 * A helper class to deal with API exceptions.
 */
final class ExceptionHelper {

  public static function handle(\Exception $exception) : void {
    $message = $exception->getMessage() ?: 'No message given';

    if ($exception instanceof ApiException) {
      $body = json_decode($exception->getResponseBody() ?? '');
      $message = $exception->getMessage() ?: 'API request failed with no error message.';

      if (isset($body->message)) {
        $message = $body->message;
      }
    }
    throw new PaymentGatewayException($message, previous: $exception);
  }

}
