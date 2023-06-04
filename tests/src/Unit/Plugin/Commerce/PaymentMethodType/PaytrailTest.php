<?php

declare(strict_types = 1);

namespace Drupal\Tests\commerce_paytrail\Unit\Plugin\Commerce\PaymentMethodType;

use Drupal\commerce_payment\Entity\PaymentMethodInterface;
use Drupal\commerce_paytrail\Plugin\Commerce\PaymentMethodType\Paytrail;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Tests\UnitTestCase;
use Prophecy\PhpUnit\ProphecyTrait;

/**
 * Tests Paytrail payment method.
 *
 * @coversDefaultClass \Drupal\commerce_paytrail\Plugin\Commerce\PaymentMethodType\Paytrail
 */
class PaytrailTest extends UnitTestCase {

  use ProphecyTrait;

  /**
   * @covers ::buildLabel
   */
  public function testBuildLabel() : void {
    $container = new ContainerBuilder();
    $container->set('string_translation', $this->getStringTranslationStub());
    \Drupal::setContainer($container);

    $paymentMethodType = $this->prophesize(PaymentMethodInterface::class);
    $sut = new Paytrail([], '', []);
    $this->assertEquals('Paytrail', $sut->buildLabel($paymentMethodType->reveal()));
  }
}
