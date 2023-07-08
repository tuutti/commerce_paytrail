<?php

declare(strict_types = 1);

namespace Drupal\Tests\commerce_paytrail\Functional;

use Drupal\commerce_payment\Entity\PaymentGateway;
use Drupal\commerce_paytrail\Plugin\Commerce\PaymentGateway\PaytrailInterface;
use Drupal\commerce_store\Entity\StoreInterface;
use Drupal\commerce_store\StoreCreationTrait;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\commerce\Traits\CommerceBrowserTestTrait;

/**
 * Provides tests for admin ui.
 *
 * @group commerce_paytrail
 */
class AdminUiTest extends BrowserTestBase {

  use CommerceBrowserTestTrait;
  use StoreCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'block',
    'field',
    'commerce',
    'commerce_price',
    'commerce_store',
    'commerce_paytrail',
  ];

  /**
   * The commerce store.
   *
   * @var \Drupal\commerce_store\Entity\StoreInterface
   */
  protected StoreInterface $store;

  /**
   * {@inheritdoc}
   */
  protected function setUp() : void {
    parent::setUp();

    $this->store = $this->createStore(currency: 'EUR');

    $user = $this->createUser([
      'administer commerce_payment_gateway',
      'view the administration theme',
      'access administration pages',
      'access commerce administration pages',
      'administer commerce_currency',
      'administer commerce_store',
      'administer commerce_store_type',
    ]);
    $this->drupalLogin($user);
  }

  /**
   * Assert paytrail payment gateway settings form values.
   *
   * @param string $plugin
   *   The plugin id.
   * @param array $values
   *   The values.
   * @param callable|null $callback
   *   The callback to run with expected values.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  private function assertFormValues(
    string $plugin,
    array $values,
    ?callable $callback = NULL
  ) : void {
    $expected = [
      "configuration[$plugin][account]" => $values['account'],
      "configuration[$plugin][secret]" => $values['secret'],
      "configuration[$plugin][language]" => $values['language'],
      "configuration[$plugin][order_discount_strategy]" => $values['discountStrategy'],
    ];

    if ($callback) {
      $this->drupalGet('admin/commerce/config/payment-gateways/manage/' . $plugin);
      $callback($expected);
    }
    $this->drupalGet('admin/commerce/config/payment-gateways/manage/' . $plugin);
    $this->assertSession()->statusCodeEquals(200);

    foreach ($expected as $field => $value) {
      $this->assertSession()->fieldValueEquals($field, $value);
    }
  }

  /**
   * Test that payment gateway can be saved.
   */
  public function testSave() : void {
    foreach (['paytrail', 'paytrail_token'] as $plugin) {
      $gateway = PaymentGateway::create([
        'id' => $plugin,
        'label' => $plugin,
        'plugin' => $plugin,
      ]);
      $gateway->save();
      // Test default credentials.
      $this->assertFormValues($plugin, [
        'account' => PaytrailInterface::ACCOUNT,
        'secret' => PaytrailInterface::SECRET,
        'language' => 'automatic',
        'discountStrategy' => '',
      ]);
      // Test that we can modify values.
      $this->assertFormValues($plugin, [
        'account' => '321',
        'secret' => '123',
        'language' => 'EN',
        'discountStrategy' => PaytrailInterface::STRATEGY_REMOVE_ITEMS,
      ], fn (array $expected) => $this->submitForm($expected, 'Save'));
    }
  }

}
