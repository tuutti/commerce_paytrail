<?php

namespace Drupal\Tests\commerce_paytrail\Kernel;

use Drupal\commerce_payment\Entity\PaymentGateway;
use Drupal\commerce_paytrail\PaymentManager;
use Drupal\commerce_paytrail\Plugin\Commerce\PaymentGateway\PaytrailBase;
use Drupal\commerce_tax\Entity\TaxType;

/**
 * Provides a base class to test PaymentManager.
 */
abstract class PaymentManagerKernelTestBase extends PaytrailKernelTestBase {

  /**
   * The payment manager.
   *
   * @var \Drupal\commerce_paytrail\PaymentManager
   */
  protected $sut;

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $eventDispatcher;

  /**
   * The payment gateway.
   *
   * @var \Drupal\commerce_payment\Entity\PaymentGateway
   */
  protected $gateway;

  public static $modules = [
    'state_machine',
    'address',
    'profile',
    'entity_reference_revisions',
    'path',
    'commerce_tax',
    'commerce_product',
    'commerce_checkout',
    'commerce_order',
    'commerce_payment',
    'commerce_paytrail',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    $this->installEntitySchema('profile');
    $this->installEntitySchema('commerce_order');
    $this->installEntitySchema('commerce_order_item');
    $this->installEntitySchema('commerce_product');
    $this->installEntitySchema('commerce_product_variation');
    $this->installEntitySchema('commerce_payment');
    $this->installEntitySchema('commerce_payment_method');
    $this->installConfig('path');
    $this->installConfig('commerce_order');
    $this->installConfig('commerce_product');
    $this->installConfig('commerce_checkout');
    $this->installConfig('commerce_payment');
    $this->installConfig('commerce_paytrail');

    $this->store->set('prices_include_tax', TRUE)->save();

    TaxType::create([
      'id' => 'vat',
      'label' => 'VAT',
      'plugin' => 'european_union_vat',
      'configuration' => [
        'display_inclusive' => TRUE,
      ],
    ])->save();

    $this->gateway = PaymentGateway::create(
      [
        'id' => 'paytrail',
        'label' => 'Paytrail',
        'plugin' => 'paytrail',
      ]
    );
    $this->gateway->getPlugin()->setConfiguration(
      [
        'culture' => 'automatic',
        'merchant_id' => '13466',
        'merchant_hash' => '6pKF4jkv97zmqBJ3ZL8gUw5DfT2NMQ',
        'allow_ipn_create_payment' => FALSE,
        'bypass_mode' => FALSE,
        'included_data' => [
          PaytrailBase::PAYER_DETAILS => 0,
          PaytrailBase::PRODUCT_DETAILS => 0,
        ],
      ]
    );
    $this->gateway->save();

    $entityTypeManager = $this->container->get('entity_type.manager');
    $this->eventDispatcher = $this->container->get('event_dispatcher');
    $time = $this->container->get('datetime.time');

    $this->sut = new PaymentManager($entityTypeManager, $this->eventDispatcher, $time);

    $account = $this->createUser([]);

    \Drupal::currentUser()->setAccount($account);
  }

}
