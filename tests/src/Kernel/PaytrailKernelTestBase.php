<?php

declare(strict_types = 1);

namespace Drupal\Tests\commerce_paytrail\Kernel;

use Drupal\commerce_payment\Entity\PaymentGateway;
use Drupal\commerce_payment\Entity\PaymentGatewayInterface;
use Drupal\Tests\commerce_order\Kernel\OrderKernelTestBase;

/**
 * Provides a base class for Paytrail kernel tests.
 */
abstract class PaytrailKernelTestBase extends OrderKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'commerce_payment',
    'commerce_paytrail',
  ];

  /**
   * The payment gateway.
   *
   * @var \Drupal\commerce_payment\Entity\PaymentGateway
   */
  protected $gateway;

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
    $this->gateway = $this->createGatewayPlugin();
  }

  /**
   * Creates a new gateway plugin.
   *
   * @return \Drupal\commerce_payment\Entity\PaymentGatewayInterface
   *   The gateway plugin.
   */
  protected function createGatewayPlugin(string $id = 'paytrail') : PaymentGatewayInterface {
    $gateway = PaymentGateway::create([
      'id' => $id,
      'label' => 'Paytrail',
      'plugin' => 'paytrail',
    ]);
    $gateway->save();
    return $gateway;
  }

}
