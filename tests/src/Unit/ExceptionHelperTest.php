<?php

declare(strict_types = 1);

namespace Drupal\Tests\commerce_paytrail\Unit;

use Drupal\commerce_payment\Exception\HardDeclineException;
use Drupal\commerce_payment\Exception\PaymentGatewayException;
use Drupal\commerce_payment\Exception\SoftDeclineException;
use Drupal\commerce_paytrail\ExceptionHelper;
use Drupal\Tests\UnitTestCase;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Prophecy\PhpUnit\ProphecyTrait;

/**
 * Tests exception helper.
 *
 * @coversDefaultClass \Drupal\commerce_paytrail\ExceptionHelper
 */
class ExceptionHelperTest extends UnitTestCase {

  use ProphecyTrait;

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
    $request = $this->prophesize(Request::class)->reveal();

    return [
      [
        PaymentGatewayException::class,
        'API request failed with no error message.',
        new RequestException('', $request, new Response()),
      ],
      [
        PaymentGatewayException::class,
        'Test body',
        new RequestException('', $request, new Response(body: json_encode(['message' => 'Test body']))),
      ],
      [
        HardDeclineException::class,
        'description',
        new RequestException('', $request, new Response(body: json_encode([
          'message' => 'Test body',
          'acquirerResponseCodeDescription' => 'description',
          'acquirerResponseCode' => '200',
        ]))),
      ],
      [
        SoftDeclineException::class,
        'description',
        new RequestException('', $request, new Response(body: json_encode([
          'message' => 'Test body',
          'acquirerResponseCodeDescription' => 'description',
          'acquirerResponseCode' => '0',
        ]))),
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
