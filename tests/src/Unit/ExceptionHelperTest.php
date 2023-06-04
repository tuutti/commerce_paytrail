<?php

declare(strict_types = 1);

namespace Drupal\Tests\commerce_paytrail\Unit;

use Drupal\commerce_payment\Exception\HardDeclineException;
use Drupal\commerce_payment\Exception\PaymentGatewayException;
use Drupal\commerce_payment\Exception\SoftDeclineException;
use Drupal\commerce_paytrail\ExceptionHelper;
use Drupal\Tests\UnitTestCase;
use Paytrail\Payment\ApiException;

/**
 * Tests exception helper.
 *
 * @coversDefaultClass \Drupal\commerce_paytrail\ExceptionHelper
 */
class ExceptionHelperTest extends UnitTestCase {

  /**
   * @covers ::handleApiException
   * @covers ::handle
   * @dataProvider exceptionData
   */
  public function testHandle(string $expectedException, string $expectedExceptionMessage, \Exception $exception) : void {
    $this->expectException($expectedException);
    $this->expectExceptionMessage($expectedExceptionMessage);
    ExceptionHelper::handle($exception);
  }

  /**
   * A data provider.
   *
   * @return array[]
   *   The data.
   */
  public function exceptionData() : array {
    return [
      [
        PaymentGatewayException::class,
        'API request failed with no error message.',
        new ApiException(),
      ],
      [
        PaymentGatewayException::class,
        'Test body',
        new ApiException(responseBody: json_encode(['message' => 'Test body'])),
      ],
      [
        HardDeclineException::class,
        'description',
        new ApiException(responseBody: json_encode([
          'message' => 'Test body',
          'acquirerResponseCodeDescription' => 'description',
          'acquirerResponseCode' => '200',
        ])),
      ],
      [
        SoftDeclineException::class,
        'description',
        new ApiException(responseBody: json_encode([
          'message' => 'Test body',
          'acquirerResponseCodeDescription' => 'description',
          'acquirerResponseCode' => '0',
        ])),
      ],
      [
        PaymentGatewayException::class,
        'body',
        new PaymentGatewayException('body'),
      ],
      [
        PaymentGatewayException::class,
        'body',
        new \Exception('body'),
      ],
    ];
  }

}
