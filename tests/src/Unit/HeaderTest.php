<?php

declare(strict_types = 1);

namespace Drupal\Tests\commerce_paytrail\Unit;

use Drupal\commerce_paytrail\Header;
use Drupal\Tests\UnitTestCase;

/**
 * Tests header DTO.
 *
 * @coversDefaultClass \Drupal\commerce_paytrail\Header
 */
class HeaderTest extends UnitTestCase {

  /**
   * @covers ::toArray
   * @covers ::__construct
   *
   * @dataProvider toArrayData
   */
  public function testToArray(
    array $expected,
    string $account,
    string $alg,
    string $method,
    string $nonce,
    int $timestamp,
    ?string $transactionId,
    ?string $platformName
  ) : void {
    $sut = new Header(
      $account,
      $alg,
      $method,
      $nonce,
      $timestamp,
      $transactionId,
      $platformName
    );
    $this->assertEquals($expected, $sut->toArray());
  }

  /**
   * Data provider for toArray test.
   *
   * @return array[]
   *   The data.
   */
  public function toArrayData() : array {
    return [
      [
        [
          'checkout-account' => 'account-test',
          'checkout-algorithm' => 'sha256',
          'checkout-method' => 'GET',
          'checkout-nonce' => '123',
          'checkout-timestamp' => 1234567,
          'platform-name' => 'drupal/commerce_paytrail',
        ],
        'account-test',
        'sha256',
        'GET',
        '123',
        1234567,
        NULL,
        NULL,
      ],
      [
        [
          'checkout-account' => 'account-test',
          'checkout-algorithm' => 'sha256',
          'checkout-method' => 'GET',
          'checkout-nonce' => '123',
          'checkout-timestamp' => '1234567',
          'platform-name' => 'platform-name',
          'checkout-transaction-id' => 'transaction-id',
        ],
        'account-test',
        'sha256',
        'GET',
        '123',
        1234567,
        'transaction-id',
        'platform-name',
      ],
    ];
  }

}
