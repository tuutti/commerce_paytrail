<?php

namespace Drupal\Tests\commerce_paytrail\Kernel;

use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_order\Entity\OrderItem;
use Drupal\commerce_paytrail\Exception\InvalidBillingException;
use Drupal\commerce_paytrail\Plugin\Commerce\PaymentGateway\PaytrailBase;
use Drupal\commerce_price\Price;
use Drupal\commerce_product\Entity\Product;
use Drupal\commerce_product\Entity\ProductVariation;
use Drupal\profile\Entity\Profile;

/**
 * PaytrailBaseTest unit tests.
 *
 * @group commerce_paytrail
 * @coversDefaultClass \Drupal\commerce_paytrail\EventSubscriber\FormAlterSubscriber
 */
class DataIncludeTest extends PaymentManagerKernelTestBase {

  /**
   * @covers ::__construct
   * @covers ::addBillingDetails
   * @covers ::addProductDetails
   */
  public function testDataIncludes() {
    $this->gateway->getPlugin()->setConfiguration(
      [
        'included_data' => [
          PaytrailBase::PAYER_DETAILS => PaytrailBase::PAYER_DETAILS,
          PaytrailBase::PRODUCT_DETAILS => PaytrailBase::PRODUCT_DETAILS,
        ],
      ]
    );
    $this->gateway->save();

    $variation = ProductVariation::create([
      'type' => 'default',
      'sku' => 'test_product',
      'title' => 'Test title',
    ]);
    $variation->setPrice(new Price('11', 'EUR'));
    $variation->save();

    $product = Product::create([
      'type' => 'default',
      'title' => 'Test product',
    ]);
    $product->addVariation($variation)
      ->save();

    $orderItem = OrderItem::create([
      'type' => 'default',
      'purchased_entity' => $variation,
    ]);
    $order = Order::create([
      'type' => 'default',
      'store_id' => $this->store,
      'mail' => 'admin@example.com',
    ]);
    $order->addItem($orderItem);
    $order->save();

    // Make sure empty billing profile throws an exception.
    try {
      $form = $this->sut->buildFormInterface($order, $this->gateway->getPlugin());
      $this->sut->dispatch($form, $this->gateway->getPlugin(), $order);
      $this->fail('Expected InvalidBillingException');
    }
    catch (InvalidBillingException $e) {
    }

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

    $this->assertEquals('1', $alter['ITEM_ID[0]']);
    $this->assertEquals('24.00', $alter['ITEM_VAT_PERCENT[0]']);

    var_dump($alter);
  }

}
