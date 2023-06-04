<?php

declare(strict_types = 1);

namespace Drupal\Tests\commerce_paytrail\Kernel;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_paytrail\Plugin\Commerce\PaymentGateway\PaytrailBase;
use Drupal\commerce_paytrail\Plugin\Commerce\PaymentGateway\PaytrailInterface;
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
    $gateways = [
      [
        'gateway' => $this->createGatewayPlugin($this->randomMachineName(), 'paytrail'),
        'config' => [
          'display_label' => 'Paytrail',
          'payment_method_types' => ['paytrail'],
        ],
      ],
      [
        'gateway' => $this->createGatewayPlugin($this->randomMachineName(), 'paytrail_token'),
        'config' => [
          'display_label' => 'Paytrail (Credit card)',
          'payment_method_types' => ['paytrail_token'],
        ],
      ],
    ];

    foreach ($gateways as $item) {
      ['gateway' => $gateway, 'config' => $config] = $item;
      $plugin = $gateway->getPlugin();

      static::assertEquals($config + [
        'language' => 'automatic',
        'account' => PaytrailInterface::ACCOUNT,
        'secret' => PaytrailInterface::SECRET,
        'mode' => 'test',
        'order_discount_strategy' => NULL,
        'collect_billing_information' => TRUE,
      ], $plugin->getConfiguration());

      static::assertFalse($plugin->isLive());
      static::assertNull($plugin->orderDiscountStrategy());

      static::assertEquals(PaytrailInterface::ACCOUNT, $plugin->getClientConfiguration()->getApiKey('account'));
      static::assertEquals(PaytrailInterface::SECRET, $plugin->getClientConfiguration()->getApiKey('secret'));
      static::assertEquals('drupal/commerce_paytrail', $plugin->getClientConfiguration()->getUserAgent());
    }
  }

  /**
   * @covers ::buildReturnUrl
   * @covers ::getNotifyUrl
   * @covers ::getCancelUrl
   * @covers ::getReturnUrl
   */
  public function testUrls() : void {
    $gateways = [
      $this->createGatewayPlugin($this->randomMachineName(), 'paytrail'),
      $this->createGatewayPlugin($this->randomMachineName(), 'paytrail_token'),
    ];

    $orderMock = $this->prophesize(OrderInterface::class);
    $orderMock->id()->willReturn('1');
    $order = $orderMock->reveal();

    foreach ($gateways as $gateway) {
      /** @var \Drupal\commerce_paytrail\Plugin\Commerce\PaymentGateway\PaytrailInterface $plugin */
      $plugin = $gateway->getPlugin();
      static::assertEquals('http://localhost/checkout/1/payment/return', $plugin->getReturnUrl($order)->toString());
      static::assertEquals('http://localhost/checkout/1/payment/cancel', $plugin->getCancelUrl($order)->toString());
      static::assertEquals('http://localhost/payment/notify/' . $gateway->id(), $plugin->getNotifyUrl()->toString());
      static::assertEquals('http://localhost/payment/notify/' . $gateway->id() . '?event=test', $plugin->getNotifyUrl('test')->toString());
    }
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

    $gateways = [
      $this->createGatewayPlugin($this->randomMachineName(), 'paytrail'),
      $this->createGatewayPlugin($this->randomMachineName(), 'paytrail_token'),
    ];

    // Test automatic language detection.
    foreach ($gateways as $gateway) {
      /** @var \Drupal\commerce_paytrail\Plugin\Commerce\PaymentGateway\PaytrailInterface $plugin */
      $plugin = $gateway->getPlugin();

      foreach ($languages as $defaultLanguage => $expected) {
        $this->config('system.site')->set('default_langcode', $defaultLanguage)->save();
        static::assertEquals($expected, $plugin->getLanguage());
      }
    }

    // Test specific language.
    foreach ($gateways as $gateway) {
      /** @var \Drupal\commerce_paytrail\Plugin\Commerce\PaymentGateway\PaytrailInterface $plugin */
      $plugin = $gateway->getPlugin();
      $plugin->setConfiguration(['language' => 'FI']);
      static::assertEquals('FI', $plugin->getLanguage());
    }
  }

}
