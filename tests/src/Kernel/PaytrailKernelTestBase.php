<?php

namespace Drupal\Tests\commerce_paytrail\Kernel;

use Drupal\commerce_store\StoreCreationTrait;
use Drupal\Tests\commerce\Kernel\CommerceKernelTestBase;

/**
 * Provides a base class for Paytrail kernel tests.
 */
abstract class PaytrailKernelTestBase extends CommerceKernelTestBase {

  use StoreCreationTrait;

  public static $modules = [
    'commerce_number_pattern',
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
  protected function setUp() {
    parent::setUp();

    $this->installSchema('system', 'router');
    $this->installEntitySchema('commerce_currency');
    $this->installEntitySchema('commerce_store');
    $this->installConfig(['commerce_store']);
    $this->installSchema('commerce_number_pattern', ['commerce_number_pattern_sequence']);

    $this->store = $this->createStore('Default store', 'admin@example.com', 'online', TRUE, 'FI', 'EUR');
  }

}
