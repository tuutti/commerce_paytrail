<?php

declare(strict_types = 1);

namespace Drupal\Tests\commerce_paytrail\Unit;

use Drupal\commerce_paytrail\Exception\SecurityHashMismatchException;
use Drupal\commerce_paytrail\Repository\Response;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;

/**
 * Response unit tests.
 *
 * @group commerce_paytrail
 * @coversDefaultClass \Drupal\commerce_paytrail\Repository\Response
 */
class ResponseTest extends UnitTestCase {

  /**
   * Tests createFromRequest().
   *
   * @covers ::createFromRequest
   * @covers ::setAuthCode
   * @covers ::setOrderNumber
   * @covers ::setPaymentId
   * @covers ::setPaymentMethod
   * @covers ::setTimestamp
   * @covers ::setPaymentStatus
   */
  public function testCreateFromRequest() {
    $request = Request::createFromGlobals();
    $request->query = new ParameterBag([
      'ORDER_NUMBER' => '123',
      'PAYMENT_ID' => '2333',
      'PAYMENT_METHOD' => '1',
      'TIMESTAMP' => time(),
      'STATUS' => 'PAID',
      'RETURN_AUTHCODE' => 'dsads',
    ]);
    $response = Response::createFromRequest('1234', $request);

    static::assertInstanceOf(Response::class, $response);
  }

  /**
   * Tests exceptions.
   *
   * @dataProvider createFromRequestExceptionData
   */
  public function testCreateFromRequestException(array $data) : void {
    $request = Request::createFromGlobals();
    $request->query = new ParameterBag($data + [
      'ORDER_NUMBER' => '123',
      'PAYMENT_ID' => '2333',
      'PAYMENT_METHOD' => '1',
      'TIMESTAMP' => time(),
      'STATUS' => 'PAID',
      'RETURN_AUTHCODE' => 'dsads',
    ]);

    $this->expectException(\InvalidArgumentException::class);
    Response::createFromRequest('1234', $request);
  }

  /**
   * The data provider for testCreateFromRequestException().
   *
   * @return array
   *   The test data.
   */
  public function createFromRequestExceptionData() {
    return [
      [['ORDER_NUMBER' => NULL]],
      [['PAYMENT_ID' => NULL]],
      [['PAYMENT_METHOD' => NULL]],
      [['TIMESTAMP' => NULL]],
      [['STATUS' => NULL]],
      [['RETURN_AUTHCODE' => NULL]],
    ];
  }

  /**
   * Tests isValidResponse().
   *
   * @covers ::isValidResponse
   * @covers ::getTimestamp
   * @covers ::getOrderNumber
   * @covers ::getPaymentId
   * @covers ::getPaymentStatus
   * @covers ::getAuthCode
   * @covers ::generateReturnChecksum
   * @covers \Drupal\commerce_paytrail\Repository\BaseResource
   */
  public function testIsValidResponse() {
    $request = Request::createFromGlobals();

    $request->query = new ParameterBag([
      'ORDER_NUMBER' => '123',
      'PAYMENT_ID' => '2333',
      'PAYMENT_METHOD' => '1',
      'TIMESTAMP' => 1512281966,
      'STATUS' => 'PAID',
      'RETURN_AUTHCODE' => 'A615F71585C0C1E04577E5B5DC79EF380045EA2644EEEA6B147049E94B8A7C49',
    ]);
    $response = Response::createFromRequest('1234', $request);
    $response->isValidResponse();
    $this->assertIsArray($response->build());
  }

  /**
   * Tests response validation exceptions.
   *
   * @dataProvider isValidResponseExceptionData
   */
  public function testIsValidResponseException(string $message, array $data) : void {
    $request = Request::createFromGlobals();
    $request->query = new ParameterBag($data + [
      'ORDER_NUMBER' => '123',
      'PAYMENT_ID' => '2333',
      'PAYMENT_METHOD' => '1',
      'TIMESTAMP' => 1512281966,
      'STATUS' => 'PAID',
      'RETURN_AUTHCODE' => 'A615F71585C0C1E04577E5B5DC79EF380045EA2644EEEA6B147049E94B8A7C49',
    ]);

    $this->expectException(SecurityHashMismatchException::class);
    $this->expectExceptionMessage($message);
    $response = Response::createFromRequest('1234', $request);
    $response->isValidResponse();
  }

  /**
   * Data provider for testIsValidResponseException.
   *
   * @return array
   *   The data.
   */
  public function isValidResponseExceptionData() : array {
    return [
      [
        'Validation failed (invalid payment state)', ['STATUS' => 'CANCELLED'],
      ],
      [
        'Validation failed (security hash mismatch)', ['RETURN_AUTHCODE' => '1234'],
      ],
    ];
  }

}
