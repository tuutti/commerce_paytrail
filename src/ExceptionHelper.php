<?php

declare(strict_types = 1);

namespace Drupal\commerce_paytrail;

use Drupal\commerce_payment\Exception\HardDeclineException;
use Drupal\commerce_payment\Exception\PaymentGatewayException;
use Drupal\commerce_payment\Exception\SoftDeclineException;
use GuzzleHttp\Exception\RequestException;

/**
 * A helper class to deal with API exceptions.
 */
final class ExceptionHelper {

  public const HARD_DECLINE_RESPONSE_CODES = [
    // Nets.
    111,
    119,
    165,
    200,
    207,
    208,
    209,
    902,
    908,
    909,
    // Amex.
    181,
    183,
    189,
    200,
  ];

  /**
   * Constructs a new payment gateway exception for given API exception.
   *
   * @param \GuzzleHttp\Exception\RequestException $exception
   *   The API exception.
   *
   * @return \Drupal\commerce_payment\Exception\PaymentGatewayException
   *   The API exception converted into PaymentGatewayException.
   */
  private static function handleApiException(RequestException $exception) : PaymentGatewayException {
    $body = json_decode($exception->getResponse()?->getBody()?->getContents() ?? '');
    $message = $exception->getMessage() ?: 'API request failed with no error message.';

    if (isset($body->message)) {
      $message = $body->message;
    }

    if (isset($body->acquirerResponseCodeDescription, $body->acquirerResponseCode)) {
      $message = $body->acquirerResponseCodeDescription;

      if (in_array((int) $body->acquirerResponseCode, self::HARD_DECLINE_RESPONSE_CODES)) {
        return new HardDeclineException($message, previous: $exception);
      }
      return new SoftDeclineException($message, previous: $exception);
    }
    return new PaymentGatewayException($message, previous: $exception);
  }

  /**
   * Exception handler for payment plugins.
   *
   * @param \Exception $exception
   *   The original exception.
   *
   * @throws \Exception
   *   The exception.
   */
  public static function handle(\Exception $exception) : void {
    if ($exception instanceof RequestException) {
      $exception = self::handleApiException($exception);
    }
    if ($exception instanceof PaymentGatewayException) {
      throw $exception;
    }
    throw new PaymentGatewayException($exception->getMessage(), previous: $exception);
  }

}
