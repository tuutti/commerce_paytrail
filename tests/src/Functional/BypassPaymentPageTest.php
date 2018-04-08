<?php

namespace Drupal\Tests\commerce_paytrail\Functional;

use Drupal\commerce_payment\Entity\PaymentGateway;
use Drupal\commerce_paytrail\Plugin\Commerce\PaymentGateway\PaytrailBase;
use Drupal\commerce_store\StoreCreationTrait;
use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Tests\commerce\Functional\CommerceBrowserTestBase;
use Drupal\Tests\commerce\FunctionalJavascript\JavascriptTestTrait;

/**
 * Class BypassPaymentPageTest.
 *
 * @group commerce_paytrail
 */
class BypassPaymentPageTest extends CommerceBrowserTestBase {

  use StoreCreationTrait;
  use JavascriptTestTrait;

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
   * The product.
   *
   * @var \Drupal\commerce_product\Entity\ProductInterface
   */
  protected $product;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $store = $this->createStore('Demo', 'demo@example.com', 'default', TRUE, 'FI', 'EUR');
    $variation = $this->createEntity('commerce_product_variation', [
      'type' => 'default',
      'sku' => strtolower($this->randomMachineName()),
      'price' => [
        'number' => '9.99',
        'currency_code' => 'EUR',
      ],
    ]);
    /** @var \Drupal\commerce_product\Entity\ProductInterface $product */
    $this->product = $this->createEntity('commerce_product', [
      'type' => 'default',
      'title' => 'My product',
      'variations' => [$variation],
      'stores' => [$store],
    ]);
    /** @var \Drupal\commerce_payment\Entity\PaymentGateway $gateway */
    $gateway = PaymentGateway::create([
      'id' => 'paytrail',
      'label' => 'Paytrail',
      'plugin' => 'paytrail',
    ]);
    $gateway->getPlugin()->setConfiguration([
      'culture' => 'automatic',
      'merchant_id' => '13466',
      'merchant_hash' => '6pKF4jkv97zmqBJ3ZL8gUw5DfT2NMQ',
      'paytrail_type' => 'S1',
      'paytrail_mode' => PaytrailBase::BYPASS_MODE,
      'visible_methods' => [1 => 1, 3 => 3, 10 => 10, 2 => 2],
    ]);
    $gateway->save();
    // Cheat so we don't need JS to interact w/ Address field widget.
    /** @var \Drupal\Core\Entity\Display\EntityFormDisplayInterface $customer_form_display */
    $customer_form_display = EntityFormDisplay::load('profile.customer.default');
    $address_component = $customer_form_display->getComponent('address');
    $address_component['settings']['default_country'] = 'FI';
    $customer_form_display->setComponent('address', $address_component);
    $customer_form_display->save();
  }

  /**
   * Test E1 paytrail type.
   */
  public function testE1Payment() {
    $gateway = PaymentGateway::load('paytrail');
    $gateway->getPlugin()->setConfiguration([
      'culture' => 'automatic',
      'merchant_id' => '13466',
      'merchant_hash' => '6pKF4jkv97zmqBJ3ZL8gUw5DfT2NMQ',
      'paytrail_type' => 'E1',
      'paytrail_mode' => PaytrailBase::BYPASS_MODE,
      'visible_methods' => [],
    ]);
    $gateway->save();

    $this->drupalGet($this->product->toUrl()->toString());
    $this->submitForm([], 'Add to cart');
    $cart_link = $this->getSession()->getPage()->findLink('your cart');
    $cart_link->click();
    $this->submitForm([], 'Checkout');
    $this->assertSession()->pageTextContains('Order Summary');
    $this->submitForm([
      'payment_information[billing_information][address][0][address][given_name]' => 'Matti',
      'payment_information[billing_information][address][0][address][family_name]' => 'Meikäläinen',
      'payment_information[billing_information][address][0][address][address_line1]' => 'Fredrikinkatu 34',
      'payment_information[billing_information][address][0][address][organization]' => 'KWD Digital',
      'payment_information[billing_information][address][0][address][locality]' => 'Helsinki',
      'payment_information[billing_information][address][0][address][postal_code]' => '00100',
    ], 'Continue to review');
    $this->assertSession()->pageTextContains('Contact information');
    $this->assertSession()->pageTextContains($this->loggedInUser->getEmail());
    $this->assertSession()->pageTextContains('Payment information');
    $this->assertSession()->pageTextContains('Paytrail');
    $this->assertSession()->pageTextContains('Order Summary');
    $this->submitForm([], 'Pay and complete purchase');

    $expected = [
      'CONTACT_FIRSTNAME' => 'Matti',
      'CONTACT_LASTNAME' => 'Meikäläinen',
      'CONTACT_COMPANY' => 'KWD Digital',
      'CONTACT_ADDR_STREET' => 'Fredrikinkatu 34',
      'CONTACT_ADDR_ZIP' => '00100',
      'CONTACT_ADDR_CITY' => 'Helsinki',
      'ITEM_TITLE[0]' => 'My product',
      'ITEM_AMOUNT[0]' => '1',
      'ITEM_PRICE[0]' => '9.99',
      'ITEM_TYPE[0]' => '1',
    ];
    // Make sure required E1 fields gets populated.
    foreach ($expected as $key => $value) {
      $this->assertSession()->elementExists('xpath', sprintf('//input[@name="%s"][@value="%s"]', $key, $value));
    }
    // Visible methods is disabled, make sure all default payment
    // methods are visible.
    $count = $this->getSession()->getPage()->findAll('css', '.payment-method-button');
    $this->assertEquals(count($count), 27);
  }

  /**
   * Tests S1 paytrail type.
   */
  public function testS1Payment() {
    $this->drupalGet($this->product->toUrl()->toString());
    $this->submitForm([], 'Add to cart');
    $cart_link = $this->getSession()->getPage()->findLink('your cart');
    $cart_link->click();
    $this->submitForm([], 'Checkout');
    $this->assertSession()->pageTextContains('Order Summary');
    $this->submitForm([
      'payment_information[billing_information][address][0][address][given_name]' => 'Matti',
      'payment_information[billing_information][address][0][address][family_name]' => 'Meikäläinen',
      'payment_information[billing_information][address][0][address][address_line1]' => 'Fredrikinkatu 34',
      'payment_information[billing_information][address][0][address][locality]' => 'Helsinki',
      'payment_information[billing_information][address][0][address][postal_code]' => '00100',
    ], 'Continue to review');
    $this->assertSession()->pageTextContains('Contact information');
    $this->assertSession()->pageTextContains($this->loggedInUser->getEmail());
    $this->assertSession()->pageTextContains('Payment information');
    $this->assertSession()->pageTextContains('Paytrail');
    $this->assertSession()->pageTextContains('Order Summary');
    $this->submitForm([], 'Pay and complete purchase');

    // Make sure only 4 buttons are visible.
    $count = $this->getSession()->getPage()->findAll('css', '.payment-method-button');
    $this->assertEquals(count($count), 4);

    foreach ([1, 2, 10] as $method_id) {
      $selector = sprintf('.payment-button-%d', $method_id);
      $button = $this->getSession()->getPage()->find('css', $selector);
      $button->press();
      $this->waitForAjaxToFinish();
      // Make sure submit button gets marked as selected.
      $found = $this->getSession()->getPage()->findAll('css', $selector . '.selected');
      $this->assertEquals(count($found), 1);
      // Make sure preselected method value is set accordingly.
      $this->assertSession()->elementExists('xpath', '//input[@name="PRESELECTED_METHOD"][@value="' . $method_id . '"]');
    }
  }

}
