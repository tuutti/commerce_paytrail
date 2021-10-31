<?php

declare(strict_types = 1);

namespace Drupal\Tests\commerce_paytrail\FunctionalJavascript;

use Drupal\commerce_payment\Entity\PaymentGateway;
use Drupal\commerce_store\StoreCreationTrait;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\Tests\block\Traits\BlockCreationTrait;
use Drupal\Tests\commerce\Traits\CommerceBrowserTestTrait;

/**
 * Tests bypass payment page feature.
 *
 * @group commerce_paytrail
 */
class BypassPaymentPageTest extends WebDriverTestBase {

  use StoreCreationTrait;
  use BlockCreationTrait;
  use CommerceBrowserTestTrait;

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'block',
    'field',
    'commerce',
    'commerce_price',
    'commerce_store',
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
   * The payment gateway.
   *
   * @var \Drupal\commerce_paytrail\Plugin\Commerce\PaymentGateway\Paytrail
   */
  protected $gateway;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp() : void {
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
    $this->gateway = PaymentGateway::create([
      'id' => 'paytrail',
      'label' => 'Paytrail',
      'plugin' => 'paytrail',
    ]);
    $this->gateway->getPlugin()->setConfiguration([
      'culture' => 'automatic',
      'merchant_id' => '13466',
      'merchant_hash' => '6pKF4jkv97zmqBJ3ZL8gUw5DfT2NMQ',
      'bypass_mode' => TRUE,
      'collect_product_details' => TRUE,
      'collect_billing_information' => TRUE,
    ]);
    $this->gateway->save();
    // Cheat so we don't need JS to interact w/ Address field widget.
    /** @var \Drupal\Core\Entity\Display\EntityFormDisplayInterface $customer_form_display */
    $customer_form_display = EntityFormDisplay::load('profile.customer.default');
    $address_component = $customer_form_display->getComponent('address');
    $address_component['settings']['default_country'] = 'FI';
    $customer_form_display->setComponent('address', $address_component);
    $customer_form_display->save();

    $this->placeBlock('local_tasks_block');
    $this->placeBlock('local_actions_block');
    $this->placeBlock('page_title_block');

    $account = $this->createUser();
    $this->drupalLogin($account);
  }

  /**
   * Test bypass mode.
   */
  public function testPayment($mail = NULL) {
    $this->drupalGet($this->product->toUrl()->toString());

    $this->submitForm([], 'Add to cart');
    $cart_link = $this->getSession()->getPage()->findLink('your cart');
    $cart_link->click();
    $this->submitForm([], 'Checkout');

    $review_form = [];

    if ($mail) {
      $this->submitForm([], 'Continue as Guest');
      $review_form['contact_information[email]'] = $mail;
      $review_form['contact_information[email_confirm]'] = $mail;
    }

    $this->assertSession()->pageTextContains('Order Summary');

    $review_form += [
      'payment_information[billing_information][address][0][address][given_name]' => 'Matti',
      'payment_information[billing_information][address][0][address][family_name]' => 'Meikäläinen',
      'payment_information[billing_information][address][0][address][address_line1]' => 'Fredrikinkatu 34',
      'payment_information[billing_information][address][0][address][organization]' => 'Druid Oy',
      'payment_information[billing_information][address][0][address][locality]' => 'Helsinki',
      'payment_information[billing_information][address][0][address][postal_code]' => '00100',
    ];
    $this->submitForm($review_form, 'Continue to review');

    $this->assertSession()->pageTextContains('Contact information');
    $this->assertSession()->pageTextContains($mail ?? $this->loggedInUser->getEmail());
    $this->assertSession()->pageTextContains('Payment information');
    $this->assertSession()->pageTextContains('Paytrail');
    $this->assertSession()->pageTextContains('Order Summary');
    $this->submitForm([], 'Pay and complete purchase');

    $expected = [
      'PAYER_PERSON_FIRSTNAME' => 'Matti',
      'PAYER_PERSON_LASTNAME' => 'Meikäläinen',
      'PAYER_COMPANY_NAME' => 'Druid Oy',
      'PAYER_PERSON_ADDR_STREET' => 'Fredrikinkatu 34',
      'PAYER_PERSON_ADDR_POSTAL_CODE' => '00100',
      'PAYER_PERSON_ADDR_TOWN' => 'Helsinki',
      'ITEM_TITLE[0]' => 'My product',
      'ITEM_QUANTITY[0]' => '1',
      'ITEM_UNIT_PRICE[0]' => '9.99',
      'ITEM_TYPE[0]' => '1',
    ];
    // Make sure required fields gets populated.
    foreach ($expected as $key => $value) {
      $this->assertSession()->elementExists('xpath', sprintf('//input[@name="%s"][@value="%s"]', $key, $value));
    }
    // Make sure all payment methods are visible by default.
    $count = $this->getSession()->getPage()->findAll('css', '.payment-method-button');
    $this->assertEquals(27, count($count));

    // Disable both; product details and payer details and make sure no
    // product or payer details are sent.
    $this->gateway->getPlugin()->setConfiguration([
      'collect_product_details' => FALSE,
      'collect_billing_information' => FALSE,
      'bypass_mode' => TRUE,
    ]);
    $this->gateway->save();

    // Flush caches to reset render cache.
    Cache::invalidateTags(['config:commerce_checkout.commerce_checkout_flow.default']);

    $this->getSession()->reload();

    // Make sure we don't get redirected to paytrail.
    $this->assertSession()->elementExists('css', '#edit-payment-process-offsite-payment');

    $expected = [
      'ITEM_TITLE[0]',
      'ITEM_QUANTITY[0]',
      'ITEM_UNIT_PRICE[0]',
      'ITEM_TYPE[0]',
      'PAYER_PERSON_FIRSTNAME',
      'PAYER_PERSON_LASTNAME',
      'PAYER_COMPANY_NAME',
      'PAYER_PERSON_ADDR_STREET',
      'PAYER_PERSON_ADDR_POSTAL_CODE',
      'PAYER_PERSON_ADDR_TOWN',
    ];
    foreach ($expected as $key) {
      $this->assertSession()->elementNotExists('xpath', sprintf('//input[@name="%s"]', $key));
    }

    /** @var \Drupal\commerce_paytrail\Entity\PaymentMethod $method */
    foreach ($this->gateway->getPlugin()->getVisibleMethods(FALSE) as $method) {
      // Disable everything but the first 3 payment methods.
      if ((int) $method->id() > 4) {
        $method->setStatus(FALSE)->save();
      }
    }
    $this->assertEqual(3, count($this->gateway->getPlugin()->getVisibleMethods()));

    // Flush caches to reset render cache.
    Cache::invalidateTags(['config:commerce_checkout.commerce_checkout_flow.default']);

    $this->getSession()->reload();

    // Make sure only 3 buttons are visible.
    $count = $this->getSession()->getPage()->findAll('css', '.payment-method-button');

    $this->assertEquals(3, count($count));

    foreach ($this->gateway->getPlugin()->getVisibleMethods() as $method) {
      $selector = sprintf('.payment-button-%d', $method->id());
      $button = $this->getSession()->getPage()->find('css', $selector);
      $button->press();
      $this->assertSession()->assertWaitOnAjaxRequest();
      // Make sure submit button gets marked as selected.
      $found = $this->getSession()->getPage()->find('css', $selector . '.selected');
      $this->assertEquals($found->getValue(), $method->label());
      // Make sure payment method value is set accordingly.
      $this->assertSession()->elementExists('xpath', '//input[@name="PAYMENT_METHODS"][@value="' . $method->id() . '"]');
    }
    // Make sure we don't get redirected to paytrail too early.
    $this->assertSession()->pageTextContains('Select payment method');
  }

  /**
   * Run same tests as an anonymous user.
   */
  public function testAnonymous() {
    $this->drupalLogout();

    $this->testPayment('admin@example.com');
  }

}
