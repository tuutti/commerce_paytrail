<?php

declare(strict_types = 1);

namespace Drupal\Tests\commerce_paytrail\Kernel;

use Drupal\commerce_paytrail\Plugin\Commerce\PaymentGateway\PaytrailBase;
use Drupal\language\Entity\ConfigurableLanguage;

/**
 * Paytrail gateway plugin tests.
 *
 * @group commerce_paytrail
 * @coversDefaultClass \Drupal\commerce_paytrail\Plugin\Commerce\PaymentGateway\Paytrail
 */
class PaytrailConfigTest extends PaytrailKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'language',
    'content_translation',
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
   */
  public function testDefaultValues() : void {
    $this->assertEquals([
      'language' => 'automatic',
      'account' => PaytrailBase::ACCOUNT,
      'secret' => PaytrailBase::SECRET,
      'display_label' => 'Paytrail',
      'mode' => 'test',
      // Payment method types is keyed incorrectly by default.
      'payment_method_types' => ['paytrail'],
      'collect_billing_information' => TRUE,
    ], $this->gateway->getPlugin()->getConfiguration());
  }

  /**
   * Tests the getLanguage() method.
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
      $this->assertEquals($expected, $plugin->getLanguage());
    }

  }

}
