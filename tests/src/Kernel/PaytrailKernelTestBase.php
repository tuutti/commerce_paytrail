<?php

declare(strict_types = 1);

namespace Drupal\Tests\commerce_paytrail\Kernel;

use Drupal\Tests\commerce_order\Kernel\OrderKernelTestBase;
use Drupal\Tests\commerce_paytrail\Traits\ApiTestTrait;

/**
 * Provides a base class for Paytrail kernel tests.
 */
abstract class PaytrailKernelTestBase extends OrderKernelTestBase {

  use ApiTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'commerce_payment',
    'commerce_paytrail',
  ];

  /**
   * The default store.
   *
   * @var \Drupal\commerce_store\Entity\StoreInterface
   */
  protected $store;

  /**
   * {@inheritdoc}
   */
  protected function setUp() : void {
    parent::setUp();

    $this->installEntitySchema('commerce_payment_method');
    $this->installConfig('commerce_payment');
    $this->installConfig('commerce_paytrail');
    $this->store = $this->createStore(country: 'FI', currency: 'EUR');
  }

}
