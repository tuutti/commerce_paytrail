<?php

declare(strict_types = 1);

namespace Drupal\Tests\commerce_paytrail\Unit\Plugin\Commerce\PaymentMethodType;

use Drupal\commerce_payment\Entity\PaymentMethodInterface;
use Drupal\commerce_paytrail\Plugin\Commerce\PaymentMethodType\PaytrailToken;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Tests\UnitTestCase;
use Prophecy\PhpUnit\ProphecyTrait;

/**
 * Tests Paytrail token payment method.
 *
 * @coversDefaultClass \Drupal\commerce_paytrail\Plugin\Commerce\PaymentMethodType\PaytrailToken
 */
class PaymentTokenTest extends UnitTestCase {

  use ProphecyTrait;

  /**
   * @covers ::buildLabel
   */
  public function testBuildLabel() : void {
    $container = new ContainerBuilder();
    $container->set('string_translation', $this->getStringTranslationStub());
    \Drupal::setContainer($container);

    $paymentMethodType = $this->prophesize(PaymentMethodInterface::class);
    $sut = new PaytrailToken([], '', []);
    $this->assertEquals('Paytrail (Credit card)', $sut->buildLabel($paymentMethodType->reveal()));

    $paymentMethodType->card_type = (object) ['value' => 'visa'];
    $paymentMethodType->card_number = (object) ['value' => '3111'];
    $this->assertEquals('Visa ending in 3111', $sut->buildLabel($paymentMethodType->reveal()));
  }

}
