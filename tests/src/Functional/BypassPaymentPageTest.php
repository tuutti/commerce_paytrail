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
      'bypass_mode' => TRUE,
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
   * Test bypass mode.
   */
  public function testPayment() {
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
      'payment_information[billing_information][address][0][address][organization]' => 'Druid Oy',
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
    $this->assertEquals(count($count), 27);

    // Disable product details and make sure no product details are sent.
    $gateway = PaymentGateway::load('paytrail');
    $gateway->getPlugin()->setConfiguration([
      'culture' => 'automatic',
      'merchant_id' => '13466',
      'merchant_hash' => '6pKF4jkv97zmqBJ3ZL8gUw5DfT2NMQ',
      'bypass_mode' => TRUE,
      'included_data' => [
        PaytrailBase::PRODUCT_DETAILS => 0,
        PaytrailBase::PAYER_DETAILS => PaytrailBase::PAYER_DETAILS,
      ],
    ]);
    $gateway->save();

    $this->getSession()->reload();

    $expected = [
      'ITEM_TITLE[0]',
      'ITEM_QUANTITY[0]',
      'ITEM_UNIT_PRICE[0]',
      'ITEM_TYPE[0]',
    ];
    foreach ($expected as $key) {
      $this->assertSession()->elementNotExists('xpath', sprintf('//input[@name="%s"]', $key));
    }

    // Disable both; product details and payer details and make sure no
    // product or payer details are sent.
    $gateway = PaymentGateway::load('paytrail');
    $gateway->getPlugin()->setConfiguration([
      'culture' => 'automatic',
      'merchant_id' => '13466',
      'merchant_hash' => '6pKF4jkv97zmqBJ3ZL8gUw5DfT2NMQ',
      'bypass_mode' => TRUE,
      'included_data' => [
        PaytrailBase::PRODUCT_DETAILS => 0,
        PaytrailBase::PAYER_DETAILS => 0,
      ],
    ]);
    $gateway->save();

    $this->getSession()->reload();

    $expected = [
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
    foreach ($gateway->getPlugin()->getVisibleMethods(FALSE) as $method) {
      // Disable everything but first 3 payment methods.
      if ((int) $method->id() > 4) {
        $method->setStatus(FALSE)->save();
      }
    }
    $this->getSession()->reload();

    // Make sure only 3 buttons are visible.
    $count = $this->getSession()->getPage()->findAll('css', '.payment-method-button');
    $this->assertEquals(count($count), 3);

    foreach ($gateway->getPlugin()->getVisibleMethods() as $method) {
      $selector = sprintf('.payment-button-%d', $method->id());
      $button = $this->getSession()->getPage()->find('css', $selector);
      $button->press();
      $this->waitForAjaxToFinish();
      // Make sure submit button gets marked as selected.
      $found = $this->getSession()->getPage()->find('css', $selector . '.selected');
      $this->assertEquals($found->getValue(), $method->label());
      // Make sure payment method value is set accordingly.
      $this->assertSession()->elementExists('xpath', '//input[@name="PAYMENT_METHODS"][@value="' . $method->id() . '"]');
    }
  }

}
