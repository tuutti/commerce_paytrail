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
    array $expected
  ) : void {
    $sut = new Header(
      $expected['checkout-account'],
      $expected['checkout-algorithm'],
      $expected['checkout-method'],
      $expected['checkout-nonce'],
      $expected['checkout-timestamp'],
      $expected['platform-name'] ?? NULL,
      $expected['checkout-transaction-id'] ?? NULL,
      $expected['checkout-tokenization-id'] ?? NULL,
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
          'checkout-tokenization-id' => 'tokenization-id',
        ],
      ],
    ];
  }

}
