<?php

namespace Drupal\Tests\commerce_paytrail\Functional;

use Drupal\commerce_payment\Entity\Payment;
use Drupal\commerce_payment\Entity\PaymentGateway;
use Drupal\commerce_paytrail\Plugin\Commerce\PaymentGateway\PaytrailBase;
use Drupal\commerce_store\StoreCreationTrait;
use Drupal\Tests\commerce_order\Functional\OrderBrowserTestBase;

/**
 * Class ReturnPageTest.
 *
 * @group commerce_paytrail
 */
class ReturnPageTest extends OrderBrowserTestBase {

  use StoreCreationTrait;

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'commerce_product',
    'commerce_cart',
    'commerce_checkout',
    'commerce_payment',
    'commerce_paytrail',
  ];

  /**
   * The payment manager.
   *
   * @var \Drupal\commerce_paytrail\PaymentManagerInterface
   */
  protected $paymentManager;

  /**
   * The merchant hash.
   *
   * @var string
   */
  protected $merchant_hash;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    /** @var \Drupal\commerce_payment\Entity\PaymentGateway $gateway */
    $gateway = PaymentGateway::create([
      'id' => 'paytrail',
      'label' => 'Paytrail',
      'plugin' => 'paytrail',
    ]);
    $this->merchant_hash = '6pKF4jkv97zmqBJ3ZL8gUw5DfT2NMQ';

    $gateway->getPlugin()->setConfiguration([
      'culture' => 'automatic',
      'merchant_id' => '13466',
      'merchant_hash' => $this->merchant_hash,
      'paytrail_type' => 'S1',
      'paytrail_mode' => PaytrailBase::NORMAL_MODE,
      'visible_methods' => [],
    ]);
    $gateway->save();

    $this->paymentManager = $this->container->get('commerce_paytrail.payment_manager');
  }

  /**
   * Tests return callbacks.
   */
  public function testReturn() {
    $order_item = $this->createEntity('commerce_order_item', [
      'type' => 'default',
      'unit_price' => [
        'number' => '999',
        'currency_code' => 'USD',
      ],
    ]);
    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $order = $this->createEntity('commerce_order', [
      'type' => 'default',
      'mail' => $this->loggedInUser->getEmail(),
      'order_items' => [$order_item],
      'uid' => $this->loggedInUser,
      'store_id' => $this->store,
      'state' => 'draft',
      'checkout_flow' => 'default',
      'checkout_step' => 'payment',
      'payment_gateway' => 'paytrail',
    ]);
    $redirect_key = $this->paymentManager->getRedirectKey($order);
    $return_url = $this->paymentManager->getReturnUrl($order, 'commerce_payment.checkout.return');

    $arguments = [
      'ORDER_NUMBER' => $order->id(),
      'TIMESTAMP' => \Drupal::time()->getRequestTime(),
      'PAID' => random_int(12345, 23456),
      'METHOD' => 1,
    ];
    $authcode = $this->paymentManager->generateReturnChecksum($this->merchant_hash, $arguments);
    // Test invalid redirect key.
    $return_code = ['RETURN_AUTHCODE' => 1234];
    $return_url = str_replace('redirect_key=' . $redirect_key, 'redirect_key=12345', $return_url);
    $this->drupalGet($return_url, ['query' => $arguments + $return_code]);
    $this->assertSession()->pageTextContains('Validation failed (redirect key mismatch).');

    // Update order back to payment step.
    $order->set('checkout_step', 'payment')->save();

    // Test with invalid authcode.
    $return_url = str_replace('redirect_key=12345', 'redirect_key=' . $redirect_key, $return_url);
    $this->drupalGet($return_url, ['query' => $arguments + $return_code]);
    $this->assertSession()->pageTextContains('Validation failed (security hash mismatch)');

    // Update order back to payment step.
    $order->set('checkout_step', 'payment')->save();

    // Test with invalid order id.
    $this->drupalGet($return_url, ['query' => $arguments + ['ORDER_NUMBER' => 5]]);
    $this->assertSession()->pageTextContains('Validation failed (security hash mismatch)');

    // Update order back to payment step.
    $order->set('checkout_step', 'payment')->save();

    // Test correct return url.
    $return_code['RETURN_AUTHCODE'] = $authcode;
    $this->drupalGet($return_url, ['query' => $arguments + $return_code]);
    $this->assertSession()->pageTextContains('Payment was processed.');

    $payment = Payment::load(1);
    $this->assertEquals('authorization', $payment->getState()->value);
    $this->assertEquals($arguments['PAID'], $payment->getRemoteId());
    $this->assertEquals('waiting_confirm', $payment->getRemoteState());

    // Test nofitifcation.
    $arguments2 = [
      'ORDER_NUMBER' => 5,
      'TIMESTAMP' => \Drupal::time()->getRequestTime(),
      'PAID' => '12345',
      'METHOD' => 1,
    ];
    // Test invalid order id.
    $notify_url = $this->paymentManager->getReturnUrl($order, 'commerce_payment.notify');
    $this->drupalGet($notify_url, ['query' => $arguments2]);
    $this->assertSession()->statusCodeEquals(404);

    // Test invalid hash.
    $arguments2['ORDER_NUMBER'] = $order->id();
    $this->drupalGet($notify_url, ['query' => $arguments2]);
    $this->assertSession()->statusCodeEquals(400);
    $this->assertSession()->pageTextContains('Hash mismatch.');

    // Test invalid PAID (transaction id).
    $hash = ['RETURN_AUTHCODE' => $this->paymentManager->generateReturnChecksum($this->merchant_hash, $arguments2)];
    $this->drupalGet($notify_url, ['query' => $arguments2 + $hash]);
    $this->assertSession()->statusCodeEquals(400);
    $this->assertSession()->pageTextContains('Transaction id mismatch');

    // Test with correct values.
    $arguments2['PAID'] = $arguments['PAID'];
    $hash = ['RETURN_AUTHCODE' => $this->paymentManager->generateReturnChecksum($this->merchant_hash, $arguments2)];
    $this->drupalGet($notify_url, ['query' => $arguments2 + $hash]);
    $this->assertSession()->statusCodeEquals(200);

    // Reset entity cache.
    /** @var Payment $payment */
    $entity_manager = $this->container->get('entity_type.manager');
    $entity_manager->getStorage('commerce_payment')->resetCache([1]);
    $payment = $entity_manager->getStorage('commerce_payment')->load(1);
    $this->assertEquals('completed', $payment->getState()->value);
    $this->assertEquals($arguments2['PAID'], $payment->getRemoteId());
    $this->assertEquals('paid', $payment->getRemoteState());
  }

}
