<?php

declare(strict_types = 1);

namespace Drupal\Tests\commerce_paytrail\Unit;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_payment\Entity\PaymentGatewayInterface as PaymentGatewayEntityInterface;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\PaymentGatewayInterface;
use Drupal\commerce_paytrail\Exception\PaytrailPluginException;
use Drupal\commerce_paytrail\PaymentGatewayPluginTrait;
use Drupal\commerce_paytrail\Plugin\Commerce\PaymentGateway\PaytrailInterface;
use Drupal\Core\TypedData\ListInterface;
use Drupal\Tests\UnitTestCase;
use Prophecy\PhpUnit\ProphecyTrait;

/**
 * Tests payment gateway plugin trait.
 */
class PaymentGatewayPluginTraitTest extends UnitTestCase {

  use PaymentGatewayPluginTrait;
  use ProphecyTrait;

  /**
   * @covers \Drupal\commerce_paytrail\PaymentGatewayPluginTrait::getPaymentPlugin
   */
  public function testGatewayNotFoundException() : void {
    $list = $this->prophesize(ListInterface::class);
    $list->isEmpty()->willReturn(TRUE);

    $order = $this->prophesize(OrderInterface::class);
    $order->id()->willReturn(1);
    $order->get('payment_gateway')
      ->willReturn(
        $list->reveal()
      );
    $this->expectException(PaytrailPluginException::class);
    $this->expectExceptionMessage('Payment gateway not found.');
    $this->getPaymentPlugin($order->reveal());
  }

  /**
   * @covers \Drupal\commerce_paytrail\PaymentGatewayPluginTrait::getPaymentPlugin
   * @dataProvider invalidPluginData
   */
  public function testInvalidPluginException(mixed $plugin) : void {
    $list = $this->prophesize(ListInterface::class);
    $list->isEmpty()->willReturn(FALSE);
    $list->first()->willReturn($plugin);

    $order = $this->prophesize(OrderInterface::class);
    $order->id()->willReturn(1);
    $order->get('payment_gateway')
      ->willReturn(
        $list->reveal()
      );
    $this->expectException(PaytrailPluginException::class);
    $this->expectExceptionMessage('Payment gateway not instanceof PaytrailInterface.');
    $this->getPaymentPlugin($order->reveal());
  }

  /**
   * Data provider for invalid plugin exception test.
   *
   * @return \Generator
   *   The data.
   */
  public function invalidPluginData() : \Generator {
    // Test with empty gateway.
    yield [(object) ['entity' => NULL]];

    // Test with non paytrail gateway.
    $plugin = $this->prophesize(PaymentGatewayInterface::class);
    $gateway = $this->prophesize(PaymentGatewayEntityInterface::class);
    $gateway->getPlugin()->willReturn($plugin->reveal());

    yield [(object) ['entity' => $gateway->reveal()]];
  }

  /**
   * @covers \Drupal\commerce_paytrail\PaymentGatewayPluginTrait::getPaymentPlugin
   */
  public function testGetPaymentPlugin() : void {
    $plugin = $this->prophesize(PaytrailInterface::class);
    $gateway = $this->prophesize(PaymentGatewayEntityInterface::class);
    $gateway->getPlugin()->willReturn($plugin->reveal());

    $list = $this->prophesize(ListInterface::class);
    $list->isEmpty()->willReturn(FALSE);
    $list->first()->willReturn((object) ['entity' => $gateway->reveal()]);

    $order = $this->prophesize(OrderInterface::class);
    $order->id()->willReturn(1);
    $order->get('payment_gateway')
      ->willReturn(
        $list->reveal()
      );
    static::assertInstanceOf(PaytrailInterface::class, $this->getPaymentPlugin($order->reveal()));
  }

}
