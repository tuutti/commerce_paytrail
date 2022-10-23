<?php

declare(strict_types = 1);

namespace Drupal\Tests\commerce_paytrail\Traits;

use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\Entity\OrderItem;
use Drupal\commerce_price\Price;
use Drupal\commerce_product\Entity\ProductVariation;
use Drupal\physical\Weight;

/**
 * Trait to test orders.
 */
trait OrderTestTrait {

  /**
   * Creates new order.
   *
   * @param \Drupal\commerce_order\Adjustment[] $itemAdjustments
   *   The order item adjustments.
   * @param array $variationValues
   *   The product variation values.
   * @param array $orderItemValues
   *   The order item values.
   *
   * @return \Drupal\commerce_order\Entity\OrderInterface
   *   The order.
   */
  protected function createOrder(
    array $itemAdjustments = [],
    array $variationValues = [],
    array $orderItemValues = [],
  ): OrderInterface {
    $variation = ProductVariation::create($variationValues + [
      'type' => 'default',
      'sku' => 'test-product-01',
      'title' => 'Hat',
      'price' => new Price('11', 'EUR'),
      'weight' => new Weight('0', 'g'),
    ]);
    $variation->save();

    /** @var \Drupal\commerce_order\Entity\OrderItemInterface $orderItem */
    $orderItem = OrderItem::create($orderItemValues + [
      'quantity' => 2,
      'type' => 'default',
      'purchased_entity' => $variation,
    ]);
    $orderItem->setUnitPrice(new Price('11', 'EUR'))
      ->setTitle($variation->getOrderItemTitle());

    if ($itemAdjustments) {
      foreach ($itemAdjustments as $adjustment) {
        $orderItem->addAdjustment($adjustment);
      }
    }
    $orderItem->save();

    $order = Order::create([
      'type' => 'default',
      'store_id' => $this->store,
      'uid' => $this->createUser(['mail' => 'admin@example.com']),
      'payment_gateway' => $this->gateway,
      'mail' => 'admin@example.com',
    ]);
    $order->addItem($orderItem);
    $order->save();

    return $order;
  }

}
