<?php

declare(strict_types = 1);

namespace Drupal\Tests\commerce_paytrail\Functional;

use Drupal\commerce_payment\Entity\PaymentGateway;
use Drupal\commerce_price\Repository\CurrencyRepository;
use Drupal\commerce_store\StoreCreationTrait;
use Drupal\Tests\block\Traits\BlockCreationTrait;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\commerce\Traits\CommerceBrowserTestTrait;

/**
 * Provides a base class for Paytrail functional tests.
 */
abstract class PaytrailBrowserTestBase extends BrowserTestBase {

  use BlockCreationTrait;
  use StoreCreationTrait;
  use CommerceBrowserTestTrait;

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'system',
    'block',
    'field',
    'commerce',
    'commerce_price',
    'commerce_store',
    'commerce_paytrail',
  ];

  /**
   * The store entity.
   *
   * @var \Drupal\commerce_store\Entity\Store
   */
  protected $store;

  /**
   * The payment gateway.
   *
   * @var \Drupal\commerce_payment\Entity\PaymentGateway
   */
  protected $gateway;

  /**
   * The paytrail gateway.
   *
   * @var \Drupal\commerce_paytrail\Plugin\Commerce\PaymentGateway\Paytrail
   */
  protected $gatewayPlugin;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp() : void {
    parent::setUp();

    $this->store = $this->createStore(currency: 'EUR');
    $this->placeBlock('local_tasks_block');
    $this->placeBlock('local_actions_block');
    $this->placeBlock('page_title_block');
    $this->gateway = PaymentGateway::create([
      'id' => 'paytrail',
      'label' => 'Paytrail',
      'plugin' => 'paytrail',
    ]);
    $this->gateway->save();
    $this->gatewayPlugin = $this->gateway->getPlugin();

    $user = $this->createUser($this->getAdministratorPermissions());
    $this->drupalLogin($user);
  }

  /**
   * Gets the permissions for the admin user.
   *
   * @return string[]
   *   The permissions.
   */
  protected function getAdministratorPermissions() : array {
    return [
      'view the administration theme',
      'access administration pages',
      'access commerce administration pages',
      'administer commerce_currency',
      'administer commerce_store',
      'administer commerce_store_type',
    ];
  }

}
