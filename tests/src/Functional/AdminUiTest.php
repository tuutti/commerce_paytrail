<?php

declare(strict_types = 1);

namespace Drupal\Tests\commerce_paytrail\Functional;

use Drupal\commerce_payment\Entity\PaymentGateway;
use Drupal\commerce_payment\Entity\PaymentGatewayInterface;
use Drupal\commerce_paytrail\Plugin\Commerce\PaymentGateway\Paytrail;
use Drupal\commerce_paytrail\Plugin\Commerce\PaymentGateway\PaytrailBase;
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
   * The payment gateway entity.
   *
   * @var \Drupal\commerce_payment\Entity\PaymentGatewayInterface
   */
  protected PaymentGatewayInterface $gateway;

  /**
   * The payment gateway.
   *
   * @var \Drupal\commerce_paytrail\Plugin\Commerce\PaymentGateway\Paytrail
   */
  protected Paytrail $gatewayPlugin;

  /**
   * {@inheritdoc}
   */
  protected function setUp() : void {
    parent::setUp();

    $this->store = $this->createStore(currency: 'EUR');
    $this->gateway = PaymentGateway::create([
      'id' => 'paytrail',
      'label' => 'Paytrail',
      'plugin' => 'paytrail',
    ]);
    $this->gateway->save();
    $this->gatewayPlugin = $this->gateway->getPlugin();

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
   * Asserts paytrail payment gateway settings form values.
   *
   * @param string $account
   *   The account.
   * @param string $secret
   *   The secret.
   * @param string $language
   *   The language.
   * @param callable|null $callback
   *   The callback to run with expected values.
   */
  private function assertFormValues(string $account, string $secret, string $language, string $discountStrategy, ?callable $callback = NULL) : void {
    $expected = [
      'configuration[paytrail][account]' => $account,
      'configuration[paytrail][secret]' => $secret,
      'configuration[paytrail][language]' => $language,
      'configuration[paytrail][order_discount_strategy]' => $discountStrategy,
    ];

    if ($callback) {
      $callback($expected);
    }
    $this->drupalGet('admin/commerce/config/payment-gateways/manage/paytrail');
    $this->assertSession()->statusCodeEquals(200);

    foreach ($expected as $field => $value) {
      $this->assertSession()->fieldValueEquals($field, $value);
    }
  }

  /**
   * Test that payment gateway can be saved.
   */
  public function testSave() : void {
    // Test default credentials.
    $this->assertFormValues(PaytrailBase::ACCOUNT, PaytrailBase::SECRET, 'automatic', '');
    // Test that we can modify values.
    $this->assertFormValues('321', '123', 'EN', PaytrailBase::STRATEGY_REMOVE_ITEMS, fn (array $expected) => $this->submitForm($expected, 'Save'));
  }

}
