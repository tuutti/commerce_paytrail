<?php

declare(strict_types = 1);

namespace Drupal\Tests\commerce_paytrail\Kernel;

use Drupal\commerce_checkout\Entity\CheckoutFlowInterface;
use Drupal\commerce_checkout\Plugin\Commerce\CheckoutFlow\CheckoutFlowWithPanesInterface;
use Drupal\commerce_checkout\Plugin\Commerce\CheckoutPane\CheckoutPaneInterface;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_paytrail\Http\PaytrailClient;
use Drupal\commerce_paytrail\Plugin\Commerce\PaymentGateway\PaytrailInterface;
use Drupal\language\Entity\ConfigurableLanguage;
use Prophecy\PhpUnit\ProphecyTrait;

/**
 * Paytrail gateway plugin tests.
 *
 * @group commerce_paytrail
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
   * @covers \Drupal\commerce_paytrail\Plugin\Commerce\PaymentGateway\PaytrailBase::getConfiguration
   * @covers \Drupal\commerce_paytrail\Plugin\Commerce\PaymentGateway\PaytrailBase::create
   * @covers \Drupal\commerce_paytrail\Plugin\Commerce\PaymentGateway\PaytrailBase::defaultConfiguration
   * @covers \Drupal\commerce_paytrail\Plugin\Commerce\PaymentGateway\PaytrailBase::getSecret
   * @covers \Drupal\commerce_paytrail\Plugin\Commerce\PaymentGateway\PaytrailBase::getAccount
   * @covers \Drupal\commerce_paytrail\Plugin\Commerce\PaymentGateway\PaytrailBase::isLive
   * @covers \Drupal\commerce_paytrail\Plugin\Commerce\PaymentGateway\PaytrailBase::setConfiguration
   * @covers \Drupal\commerce_paytrail\Plugin\Commerce\PaymentGateway\PaytrailBase::orderDiscountStrategy
   * @covers \Drupal\commerce_paytrail\Plugin\Commerce\PaymentGateway\Paytrail::create
   * @covers \Drupal\commerce_paytrail\Plugin\Commerce\PaymentGateway\PaytrailToken::create
   * @covers \Drupal\commerce_paytrail\Plugin\Commerce\PaymentGateway\Paytrail::getConfiguration
   * @covers \Drupal\commerce_paytrail\Plugin\Commerce\PaymentGateway\Paytrail::defaultConfiguration
   * @covers \Drupal\commerce_paytrail\Plugin\Commerce\PaymentGateway\Paytrail::getSecret
   * @covers \Drupal\commerce_paytrail\Plugin\Commerce\PaymentGateway\Paytrail::getAccount
   * @covers \Drupal\commerce_paytrail\Plugin\Commerce\PaymentGateway\Paytrail::orderDiscountStrategy
   * @covers \Drupal\commerce_paytrail\Plugin\Commerce\PaymentGateway\Paytrail::isLive
   * @covers \Drupal\commerce_paytrail\Plugin\Commerce\PaymentGateway\Paytrail::setConfiguration
   * @covers \Drupal\commerce_paytrail\Plugin\Commerce\PaymentGateway\PaytrailToken::getConfiguration
   * @covers \Drupal\commerce_paytrail\Plugin\Commerce\PaymentGateway\PaytrailToken::defaultConfiguration
   * @covers \Drupal\commerce_paytrail\Plugin\Commerce\PaymentGateway\PaytrailToken::getSecret
   * @covers \Drupal\commerce_paytrail\Plugin\Commerce\PaymentGateway\PaytrailToken::getAccount
   * @covers \Drupal\commerce_paytrail\Plugin\Commerce\PaymentGateway\PaytrailToken::orderDiscountStrategy
   * @covers \Drupal\commerce_paytrail\Plugin\Commerce\PaymentGateway\PaytrailToken::isLive
   * @covers \Drupal\commerce_paytrail\Plugin\Commerce\PaymentGateway\PaytrailToken::setConfiguration
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
          'capture' => TRUE,
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

      static::assertEquals(PaytrailInterface::ACCOUNT, $plugin->getAccount());
      static::assertEquals(PaytrailInterface::SECRET, $plugin->getSecret());
    }
  }

  /**
   * @covers \Drupal\commerce_paytrail\Plugin\Commerce\PaymentGateway\PaytrailBase::buildReturnUrl
   * @covers \Drupal\commerce_paytrail\Plugin\Commerce\PaymentGateway\PaytrailBase::getNotifyUrl
   * @covers \Drupal\commerce_paytrail\Plugin\Commerce\PaymentGateway\PaytrailBase::getCancelUrl
   * @covers \Drupal\commerce_paytrail\Plugin\Commerce\PaymentGateway\PaytrailBase::getReturnUrl
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
   * @covers \Drupal\commerce_paytrail\Plugin\Commerce\PaymentGateway\PaytrailBase::getLanguage
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

  /**
   * @covers \Drupal\commerce_paytrail\Plugin\Commerce\PaymentGateway\PaytrailToken::autoCaptureEnabled
   */
  public function testAutoCapture() : void {
    $gateway = $this->createGatewayPlugin($this->randomMachineName(), 'paytrail_token');
    $plugin = $gateway->getPlugin();
    static::assertTrue($gateway->getPluginConfiguration()['capture']);
    $plugin->setConfiguration(['capture' => FALSE]);
    static::assertFalse($plugin->getConfiguration()['capture']);

    /** @var \Drupal\commerce_paytrail\Plugin\Commerce\PaymentGateway\PaytrailToken $plugin */
    $gateway = $this->createGatewayPlugin($this->randomMachineName(), 'paytrail_token');
    $plugin = $gateway->getPlugin();

    $pane = $this->prophesize(CheckoutPaneInterface::class);
    $pane->getConfiguration()
      ->willReturn(
        ['capture' => FALSE],
        ['capture' => TRUE],
      );

    $checkoutFlowPlugin = $this->prophesize(CheckoutFlowWithPanesInterface::class);
    $checkoutFlowPlugin->getPane('payment_process')
      ->willReturn(
        NULL,
        $pane->reveal(),
      );
    $checkoutFlowEntity = $this->prophesize(CheckoutFlowInterface::class);
    $checkoutFlowEntity->getPlugin()
      ->willReturn(
        NULL,
        $checkoutFlowPlugin->reveal()
      );

    $order = $this->prophesize(OrderInterface::class);
    $order->hasField('checkout_flow')
      ->willReturn(
        FALSE,
        TRUE
      );
    $order->get('checkout_flow')
      ->willReturn(
        NULL,
        (object) ['entity' => $checkoutFlowEntity->reveal()]
      );

    $order = $order->reveal();

    // Should return TRUE when checkout module is not enabled.
    static::assertTrue($plugin->autoCaptureEnabled($order));
    // Should be TRUE when order returns no checkout flow (NULL).
    static::assertTrue($plugin->autoCaptureEnabled($order));
    // Should be TRUE when checkout flow returns no plugin or configuration.
    static::assertTrue($plugin->autoCaptureEnabled($order));
    static::assertTrue($plugin->autoCaptureEnabled($order));
    // Should be FALSE when checkout pane's capture is set to FALSE.
    static::assertFalse($plugin->autoCaptureEnabled($order));
    // Should be TRUE when checkout pane's capture is set to TRUE.
    static::assertTrue($plugin->autoCaptureEnabled($order));
  }

  /**
   * @covers \Drupal\commerce_paytrail\Plugin\Commerce\PaymentGateway\PaytrailBase::getClient
   * @covers \Drupal\commerce_paytrail\Plugin\Commerce\PaymentGateway\Paytrail::getClient
   * @covers \Drupal\commerce_paytrail\Plugin\Commerce\PaymentGateway\PaytrailToken::getClient
   * @covers \Drupal\commerce_paytrail\Http\PaytrailClientFactory::__construct
   * @covers \Drupal\commerce_paytrail\Http\PaytrailClientFactory::create
   * @covers \Drupal\commerce_paytrail\Http\PaytrailClient::__construct
   */
  public function testGetClient() : void {
    $gateways = [
      $this->createGatewayPlugin($this->randomMachineName(), 'paytrail'),
      $this->createGatewayPlugin($this->randomMachineName(), 'paytrail_token'),
    ];

    // Test automatic language detection.
    foreach ($gateways as $gateway) {
      /** @var \Drupal\commerce_paytrail\Plugin\Commerce\PaymentGateway\PaytrailInterface $plugin */
      $plugin = $gateway->getPlugin();
      $this->assertInstanceOf(PaytrailClient::class, $plugin->getClient());
    }
  }

}
