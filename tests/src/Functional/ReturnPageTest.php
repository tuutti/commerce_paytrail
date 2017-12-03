<?php

namespace Drupal\Tests\commerce_paytrail\Functional;

use Drupal\commerce_payment\Entity\Payment;
use Drupal\commerce_payment\Entity\PaymentGateway;
use Drupal\commerce_paytrail\Plugin\Commerce\PaymentGateway\PaytrailBase;
use Drupal\commerce_paytrail\Repository\Response;
use Drupal\commerce_store\StoreCreationTrait;
use Drupal\Tests\commerce_order\Functional\OrderBrowserTestBase;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;

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
      'bypass_mode' => FALSE,
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
    $return_url = $this->paymentManager->getReturnUrl($order, 'commerce_payment.checkout.return');

    $request = Request::createFromGlobals();

    $request->query = new ParameterBag([
      'ORDER_NUMBER' => $order->id(),
      'PAYMENT_ID' => '123d',
      'PAYMENT_METHOD' => '1',
      'TIMESTAMP' => \Drupal::time()->getRequestTime(),
      'STATUS' => 'CANCELLED',
      'RETURN_AUTHCODE' => '1234',
    ]);

    $response = Response::createFromRequest($this->merchant_hash, $order, $request);

    $query = $response->getHashValues();
    $query['RETURN_AUTHCODE'] = '1234';

    // Test with invalid payment state.
    $this->drupalGet($return_url, ['query' => $query]);
    $this->assertSession()->pageTextContains('Validation failed due to security hash mismatch (payment_state).');

    $request->query = new ParameterBag([
      'ORDER_NUMBER' => $order->id(),
      'PAYMENT_ID' => '123d',
      'PAYMENT_METHOD' => '1',
      'TIMESTAMP' => \Drupal::time()->getRequestTime(),
      'STATUS' => 'PAID',
      'RETURN_AUTHCODE' => '1234',
    ]);
    $response = Response::createFromRequest($this->merchant_hash, $order, $request);
    $authcode = $response->generateReturnChecksum($response->getHashValues());
    $query = $response->getHashValues();
    $query['RETURN_AUTHCODE'] = '1234';

    $this->drupalGet($return_url, ['query' => $query]);
    $this->assertSession()->pageTextContains('Validation failed due to security hash mismatch (hash_mismatch).');

    // Test with invalid order id.
    $query['RETURN_AUTHCODE'] = $authcode;

    $this->drupalGet($return_url, ['query' => ['ORDER_NUMBER' => '5'] + $query]);
    $this->assertSession()->pageTextContains('Validation failed due to security hash mismatch (order_number).');

    // Test correct return url.
    $this->drupalGet($return_url, ['query' => $query]);
    $this->assertSession()->pageTextContains('Payment was processed.');

    $payment = Payment::load(1);
    $this->assertEquals('authorization', $payment->getState()->value);
    $this->assertEquals($response->getPaymentId(), $payment->getRemoteId());
    $this->assertEquals('PAID', $payment->getRemoteState());

    // Test nofitifcation.
    $request->query = new ParameterBag([
      'ORDER_NUMBER' => '5',
      'PAYMENT_ID' => '123d',
      'PAYMENT_METHOD' => '1',
      'TIMESTAMP' => \Drupal::time()->getRequestTime(),
      'STATUS' => 'CANCELLED',
      'RETURN_AUTHCODE' => '1234',
    ]);

    $response = Response::createFromRequest($this->merchant_hash, $order, $request);
    $query = $response->getHashValues();

    $query['RETURN_AUTHCODE'] = '1234';

    $notify_url = $this->paymentManager->getReturnUrl($order, 'commerce_payment.notify');

    // Test invalid order id.
    $this->drupalGet($notify_url, ['query' => $query]);
    $this->assertSession()->statusCodeEquals(404);

    // Test invalid payment state.
    $query['ORDER_NUMBER'] = $order->id();
    $this->drupalGet($notify_url, ['query' => $query]);
    $this->assertSession()->statusCodeEquals(400);
    $this->assertSession()->pageTextContains('Hash mismatch (payment_state).');

    // Test invalid hash.
    $query['STATUS'] = 'PAID';
    $this->drupalGet($notify_url, ['query' => $query]);
    $this->assertSession()->statusCodeEquals(400);
    $this->assertSession()->pageTextContains('Hash mismatch (hash_mismatch).');

    // Test with correct values.
    $request->query = new ParameterBag([
      'ORDER_NUMBER' => $order->id(),
      'PAYMENT_ID' => '123d',
      'PAYMENT_METHOD' => '1',
      'TIMESTAMP' => \Drupal::time()->getRequestTime(),
      'STATUS' => 'PAID',
      'RETURN_AUTHCODE' => '1234',
    ]);
    $response = Response::createFromRequest($this->merchant_hash, $order, $request);
    $query = $response->getHashValues();
    $query['RETURN_AUTHCODE'] = $response->generateReturnChecksum($response->getHashValues());

    $this->drupalGet($notify_url, ['query' => $query]);
    $this->assertSession()->statusCodeEquals(200);

    /** @var \Drupal\commerce_payment\Entity\Payment $payment */
    $entity_manager = $this->container->get('entity_type.manager');
    $entity_manager->getStorage('commerce_payment')->resetCache([1]);
    $payment = $entity_manager->getStorage('commerce_payment')->load(1);
    $this->assertEquals('completed', $payment->getState()->value);
    $this->assertEquals($response->getPaymentId(), $payment->getRemoteId());
    $this->assertEquals('PAID', $payment->getRemoteState());
  }

}
