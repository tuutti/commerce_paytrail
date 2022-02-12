<?php

declare(strict_types = 1);

namespace Drupal\Tests\commerce_paytrail\Kernel;

use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\Entity\OrderItem;
use Drupal\commerce_paytrail\Event\ModelEvent;
use Drupal\commerce_price\Price;
use Drupal\commerce_tax\Entity\TaxType;
use Drupal\Tests\commerce_paytrail\Traits\EventSubscriberTestTrait;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Base class for request builder tests.
 */
abstract class RequestBuilderKernelTestBase extends PaytrailKernelTestBase implements EventSubscriberInterface {

  use EventSubscriberTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'commerce_tax',
    'commerce_checkout',
    'commerce_promotion',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('profile');
    $this->installEntitySchema('commerce_payment');
    $this->installEntitySchema('commerce_promotion');
    $this->installConfig([
      'commerce_tax',
      'commerce_checkout',
      'commerce_promotion',
    ]);

    TaxType::create([
      'id' => 'vat',
      'label' => 'VAT',
      'plugin' => 'european_union_vat',
      'configuration' => [
        'display_inclusive' => TRUE,
      ],
    ])->save();

    $this->store->set('prices_include_tax', FALSE)
      ->save();
  }

  /**
   * {@inheritdoc}
   */
  public static function getEventClassName(): string {
    return ModelEvent::class;
  }

  /**
   * Creates new order.
   *
   * @param \Drupal\commerce_order\Adjustment[] $itemAdjustments
   *   The order item adjustments.
   *
   * @return \Drupal\commerce_order\Entity\OrderInterface
   *   The order.
   */
  protected function createOrder(array $itemAdjustments = []): OrderInterface {
    /** @var \Drupal\commerce_order\Entity\OrderItemInterface $orderItem */
    $orderItem = OrderItem::create([
      'type' => 'default',
    ]);
    $orderItem->setUnitPrice(new Price('11', 'EUR'))
      ->setTitle('Title')
      ->setQuantity(2);

    if ($itemAdjustments) {
      foreach ($itemAdjustments as $adjustment) {
        $orderItem->addAdjustment($adjustment);
      }
    }
    $orderItem->save();

    $order = Order::create([
      'type' => 'default',
      'store_id' => $this->store,
      'payment_gateway' => $this->gateway,
    ]);
    $order->addItem($orderItem);
    $order->save();

    return $order;
  }

}
