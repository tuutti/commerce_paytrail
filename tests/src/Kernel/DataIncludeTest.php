<?php

namespace Drupal\Tests\commerce_paytrail\Kernel;

use Drupal\commerce_order\Adjustment;
use Drupal\commerce_price\Price;
use Drupal\commerce_product\Entity\Product;
use Drupal\commerce_product\Entity\ProductVariation;
use Drupal\profile\Entity\Profile;

/**
 * Test data includes.
 *
 * @group commerce_paytrail
 * @coversDefaultClass \Drupal\commerce_paytrail\EventSubscriber\FormAlterSubscriber
 */
class DataIncludeTest extends PaymentManagerKernelTestBase {

  /**
   * The product.
   *
   * @var \Drupal\commerce_product\Entity\ProductInterface
   */
  protected $product;

  /**
   * The product variation.
   *
   * @var \Drupal\commerce_product\Entity\ProductVariationInterface
   */
  protected $variation;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    $this->variation = ProductVariation::create([
      'type' => 'default',
      'sku' => 'test_product',
      'title' => 'Test title',
    ]);
    $this->variation->setPrice(new Price('123', 'EUR'));
    $this->variation->save();

    $this->product = Product::create([
      'type' => 'default',
      'title' => 'Test product',
    ]);
    $this->product->addVariation($this->variation)
      ->save();
  }

  /**
   * Tests payment gateway with no data includes.
   */
  public function testNoIncludes() {
    $this->gateway->getPlugin()->setConfiguration(
      [
        'collect_product_details' => FALSE,
        'collect_billing_information' => FALSE,
      ]
    );
    $this->gateway->save();

    $order = $this->createOrder();

    $form = $this->sut->buildFormInterface($order, $this->gateway->getPlugin());
    $alter = $this->sut->dispatch($form, $this->gateway->getPlugin(), $order);

    $this->assertNotEmpty($alter['AUTHCODE']);

    // Make sure we don't send billing or product details when disabled.
    foreach ($alter as $key => $value) {
      $this->assertTrue(strpos('PAYER_', $key) === FALSE);
      $this->assertTrue(strpos('ITEM_', $key) === FALSE);
    }
  }

  /**
   * @covers ::addProductDetails
   */
  public function testProductDetails() {
    $this->gateway->getPlugin()->setConfiguration(
      [
        'collect_product_details' => TRUE,
        'collect_billing_information' => FALSE,
      ]
    );
    $this->gateway->save();

    $order = $this->createOrder();

    $required = [
      'ITEM_TYPE[0]' => 1,
      'ITEM_QUANTITY[0]' => '2',
      'ITEM_TITLE[0]' => 'Title',
      'ITEM_UNIT_PRICE[0]' => '11',
      'ITEM_VAT_PERCENT[0]' => '24',
    ];

    $form = $this->sut->buildFormInterface($order, $this->gateway->getPlugin());
    $alter = $this->sut->dispatch($form, $this->gateway->getPlugin(), $order);

    foreach ($required as $key => $value) {
      $this->assertEqual($value, $alter[$key]);
    }
  }

  /**
   * Make sure we can add discounts.
   */
  public function testDiscount() {
    $this->gateway->getPlugin()->setConfiguration(
      [
        'collect_product_details' => TRUE,
      ]
    );
    $this->gateway->save();

    $order = $this->createOrder([
      new Adjustment([
        'type' => 'custom',
        'label' => 'Discount',
        'amount' => new Price('-5', 'EUR'),
        'percentage' => NULL,
      ]),
    ]);

    $required = [
      'ITEM_TYPE[0]' => 1,
      'ITEM_QUANTITY[0]' => '2',
      'ITEM_TITLE[0]' => 'Title',
      // The adjustment is split between two items (see quantity) and
      // substracted before taxes = (total price-discount)/quantity = (22-5)/2.
      'ITEM_UNIT_PRICE[0]' => '8.5',
      'ITEM_VAT_PERCENT[0]' => '24',
    ];

    $form = $this->sut->buildFormInterface($order, $this->gateway->getPlugin());
    $alter = $this->sut->dispatch($form, $this->gateway->getPlugin(), $order);

    foreach ($required as $key => $value) {
      $this->assertEqual($value, $alter[$key]);
    }
  }

  /**
   * @covers ::addBillingDetails
   */
  public function testBillingDetails() {
    $this->gateway->getPlugin()->setConfiguration(
      [
        'collect_product_details' => TRUE,
        'collect_billing_information' => TRUE,
      ]
    );
    $this->gateway->save();

    $order = $this->createOrder();
    $profile = Profile::create([
      'type' => 'customer',
      'uid' => $order->getCustomerId(),
    ]);
    $profile->set('address', [
      'country_code' => 'FI',
    ]);
    $profile->save();

    $order->setBillingProfile($profile)
      ->save();

    $form = $this->sut->buildFormInterface($order, $this->gateway->getPlugin());
    $alter = $this->sut->dispatch($form, $this->gateway->getPlugin(), $order);

    $this->assertEquals('FI', $alter['PAYER_PERSON_ADDR_COUNTRY']);
  }

  /**
   * Make sure taxes are collected correctly when prices don't include taxes.
   */
  public function testPricesNotIncludingTaxes() {
    $this->gateway->getPlugin()->setConfiguration(
      [
        'collect_product_details' => TRUE,
        'collect_billing_information' => TRUE,
      ]
    );
    $this->gateway->save();
    $this->store->set('prices_include_tax', FALSE)->save();

    $order = $this->createOrder();
    $form = $this->sut->buildFormInterface($order, $this->gateway->getPlugin());
    $alter = $this->sut->dispatch($form, $this->gateway->getPlugin(), $order);

    // Make sure taxes are added correctly when taxes are not included
    // in prices.
    $this->assertEqual('13.64', $alter['ITEM_UNIT_PRICE[0]']);
    $this->assertEquals('2', $alter['ITEM_QUANTITY[0]']);
  }

}
