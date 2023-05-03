<?php

declare(strict_types = 1);

namespace Drupal\Tests\commerce_paytrail\Kernel;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_paytrail\Plugin\Commerce\PaymentGateway\PaytrailBase;
use Drupal\language\Entity\ConfigurableLanguage;
use Prophecy\PhpUnit\ProphecyTrait;

/**
 * Paytrail gateway plugin tests.
 *
 * @group commerce_paytrail
 * @coversDefaultClass \Drupal\commerce_paytrail\Plugin\Commerce\PaymentGateway\Paytrail
 */
class PaytrailConfigTest extends PaytrailKernelTestBase {

  use ProphecyTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'language',
    'content_translation',
    'commerce_checkout',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() : void {
    parent::setUp();

    foreach (['fi', 'de'] as $langcode) {
      ConfigurableLanguage::createFromLangcode($langcode)->save();
    }
  }

  /**
   * Tests the default configuration.
   *
   * @covers ::getConfiguration
   * @covers ::create
   * @covers ::defaultConfiguration
   * @covers ::setConfiguration
   * @covers ::isLive
   * @covers ::orderDiscountStrategy
   * @covers ::getClientConfiguration
   */
  public function testDefaultValues() : void {
    /** @var \Drupal\commerce_paytrail\Plugin\Commerce\PaymentGateway\Paytrail $plugin */
    $plugin = $this->gateway->getPlugin();
    static::assertEquals([
      'language' => 'automatic',
      'account' => PaytrailBase::ACCOUNT,
      'secret' => PaytrailBase::SECRET,
      'display_label' => 'Paytrail',
      'mode' => 'test',
      // Payment method types is keyed incorrectly by default.
      'payment_method_types' => ['paytrail'],
      'order_discount_strategy' => NULL,
      'collect_billing_information' => TRUE,
    ], $plugin->getConfiguration());

    static::assertFalse($plugin->isLive());
    static::assertNull($plugin->orderDiscountStrategy());

    static::assertEquals(PaytrailBase::ACCOUNT, $plugin->getClientConfiguration()->getApiKey('account'));
    static::assertEquals(PaytrailBase::SECRET, $plugin->getClientConfiguration()->getApiKey('secret'));
    static::assertEquals('drupal/commerce_paytrail', $plugin->getClientConfiguration()->getUserAgent());
  }

  /**
   * @covers ::buildReturnUrl
   * @covers ::getNotifyUrl
   * @covers ::getCancelUrl
   * @covers ::getReturnUrl
   */
  public function testUrls() : void {
    $orderMock = $this->prophesize(OrderInterface::class);
    $orderMock->id()->willReturn('1');
    $order = $orderMock->reveal();

    /** @var \Drupal\commerce_paytrail\Plugin\Commerce\PaymentGateway\Paytrail $plugin */
    $plugin = $this->gateway->getPlugin();

    static::assertEquals('http://localhost/checkout/1/payment/return', $plugin->getReturnUrl($order)->toString());
    static::assertEquals('http://localhost/checkout/1/payment/cancel', $plugin->getCancelUrl($order)->toString());
    static::assertEquals('http://localhost/payment/notify/paytrail', $plugin->getNotifyUrl()->toString());
    static::assertEquals('http://localhost/payment/notify/paytrail?event=test', $plugin->getNotifyUrl('test')->toString());
  }

  /**
   * Tests the getLanguage() method.
   *
   * @covers ::getConfiguration
   * @covers ::create
   * @covers ::defaultConfiguration
   * @covers ::setConfiguration
   * @covers ::getLanguage
   */
  public function testLanguage() : void {
    $languages = [
      'en' => 'EN',
      'fi' => 'FI',
      'de' => 'EN',
    ];

    foreach ($languages as $defaultLanguage => $expected) {
      $this->config('system.site')->set('default_langcode', $defaultLanguage)->save();
      /** @var \Drupal\commerce_paytrail\Plugin\Commerce\PaymentGateway\Paytrail $plugin */
      $plugin = $this->gateway->getPlugin();
      static::assertEquals($expected, $plugin->getLanguage());
    }
  }

}
