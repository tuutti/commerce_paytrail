<?php

declare(strict_types = 1);

namespace Drupal\Tests\commerce_paytrail\Unit;

use Drupal\commerce_paytrail\Exception\SecurityHashMismatchException;
use Drupal\commerce_paytrail\SignatureTrait;
use Drupal\Tests\UnitTestCase;
use Paytrail\SDK\Util\Signature;
use Prophecy\PhpUnit\ProphecyTrait;

/**
 * Tests signature trait.
 */
class SignatureTraitTest extends UnitTestCase {

  use SignatureTrait;
  use ProphecyTrait;

  /**
   * @covers \Drupal\commerce_paytrail\SignatureTrait::validateSignature
   * @dataProvider headerDataProvider
   */
  public function testValidateSignature(array $flatHeaders, array $arrayHeaders) : void {
    $secret = '123';
    $arrayHeaders['signature'] = Signature::calculateHmac($flatHeaders, secretKey: $secret);
    $this->validateSignature($secret, $arrayHeaders);

    unset($arrayHeaders['signature']);
    $arrayHeaders['signature'] = Signature::calculateHmac($flatHeaders, secretKey: $secret);
    $this->validateSignature($secret, $arrayHeaders);
  }

  /**
   * A header data.
   *
   * @return array
   *   The data.
   */
  public function headerDataProvider() : array {
    $flatHeaders = [
      'checkout-1' => 1,
      'checkout-2' => 2,
      'checkout-3' => 3,
      'random-header' => 3,
    ];
    $arrayHeaders = [
      'checkout-1' => [1],
      'checkout-2' => [2],
      'checkout-3' => [3],
      // Append random header to make sure only checkout-prefixed headers
      // are taken into account.
      'some-other-header' => ['dsads'],
    ];

    return [
      [
        $flatHeaders,
        $arrayHeaders,
      ],
    ];
  }

  /**
   * @covers \Drupal\commerce_paytrail\SignatureTrait::validateSignature
   */
  public function testSignatureMissingException() : void {
    $secret = '123';
    $this->expectException(SecurityHashMismatchException::class);
    $this->expectExceptionMessage('Signature missing.');
    $this->validateSignature($secret, []);
  }

  /**
   * @covers \Drupal\commerce_paytrail\SignatureTrait::validateSignature
   */
  public function testSecretMissingException() : void {
    $this->expectException(SecurityHashMismatchException::class);
    $this->expectExceptionMessage('Signature missing.');
    $this->validateSignature('', ['signature' => '123']);
  }

  /**
   * @covers \Drupal\commerce_paytrail\SignatureTrait::validateSignature
   */
  public function testInvalidSignatureException() : void {
    $secret = '123';
    $this->expectException(SecurityHashMismatchException::class);
    $this->expectExceptionMessage('Signature does not match');
    $this->validateSignature($secret, ['signature' => '123']);
  }

}
