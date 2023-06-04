<?php

declare(strict_types = 1);

namespace Drupal\Tests\commerce_paytrail\Unit;

use Drupal\commerce_paytrail\Exception\SecurityHashMismatchException;
use Drupal\commerce_paytrail\Plugin\Commerce\PaymentGateway\PaytrailInterface;
use Drupal\commerce_paytrail\SignatureTrait;
use Drupal\Tests\UnitTestCase;
use Paytrail\Payment\Configuration;
use Prophecy\PhpUnit\ProphecyTrait;

/**
 * Tests signature trait.
 */
class SignatureTraitTest extends UnitTestCase {

  use SignatureTrait;
  use ProphecyTrait;

  /**
   * @covers \Drupal\commerce_paytrail\SignatureTrait::signature
   * @dataProvider headerDataProvider
   */
  public function testSignature(array $flatHeaders, array $arrayHeaders) : void {
    $this->assertEquals($this->signature('123', $flatHeaders), $this->signature('123', $arrayHeaders));
  }

  /**
   * @covers \Drupal\commerce_paytrail\SignatureTrait::validateSignature
   * @dataProvider headerDataProvider
   */
  public function testValidateSignature(array $flatHeaders, array $arrayHeaders) : void {
    $secret = '123';
    $plugin = $this->prophesize(PaytrailInterface::class);
    $plugin->getClientConfiguration()->willReturn(
      (new Configuration())
        ->setApiKey('secret', $secret)
    );

    $arrayHeaders['signature'] = $this->signature($secret, $flatHeaders);
    $this->validateSignature($plugin->reveal(), $arrayHeaders);

    unset($arrayHeaders['signature']);
    $arrayHeaders['signature'][] = $this->signature($secret, $flatHeaders);
    $this->validateSignature($plugin->reveal(), $arrayHeaders);
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
    $plugin = $this->prophesize(PaytrailInterface::class);
    $plugin->getClientConfiguration()->willReturn(
      (new Configuration())
        ->setApiKey('secret', $secret)
    );

    $this->expectException(SecurityHashMismatchException::class);
    $this->expectExceptionMessage('Signature missing.');
    $this->validateSignature($plugin->reveal(), []);
  }

  /**
   * @covers \Drupal\commerce_paytrail\SignatureTrait::validateSignature
   */
  public function testInvalidSignatureException() : void {
    $secret = '123';
    $plugin = $this->prophesize(PaytrailInterface::class);
    $plugin->getClientConfiguration()->willReturn(
      (new Configuration())
        ->setApiKey('secret', $secret)
    );

    $this->expectException(SecurityHashMismatchException::class);
    $this->expectExceptionMessage('Signature does not match');
    $this->validateSignature($plugin->reveal(), ['signature' => '123']);
  }

}
