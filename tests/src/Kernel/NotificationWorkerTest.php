<?php

declare(strict_types = 1);

namespace Drupal\Tests\commerce_paytrail\Kernel;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_paytrail\Plugin\QueueWorker\NotificationWorker;
use Drupal\commerce_paytrail\RequestBuilder\PaymentRequestBuilderInterface;
use Drupal\Core\Queue\QueueInterface;
use Paytrail\Payment\Model\Payment;
use Prophecy\Argument;

/**
 * Paytrail gateway plugin tests.
 *
 * @group commerce_paytrail
 * @coversDefaultClass \Drupal\commerce_paytrail\Plugin\QueueWorker\NotificationWorker
 */
class NotificationWorkerTest extends RequestBuilderKernelTestBase {

  /**
   * Gets the queue.
   *
   * @return \Drupal\Core\Queue\QueueInterface
   *   The queue.
   */
  private function getQueue() : QueueInterface {
    return $this->container->get('queue')->get('commerce_paytrail_notification_worker');
  }

  /**
   * Claims one item from queue.
   */
  private function claimQueueItem() : void {
    $queue = $this->getQueue();
    /** @var \Drupal\Core\Queue\QueueWorkerManagerInterface $queueManager */
    $queueManager = $this->container->get('plugin.manager.queue_worker');
    /** @var \Drupal\Core\Queue\QueueWorkerInterface $queueWorker */
    $queueWorker = $queueManager->createInstance('commerce_paytrail_notification_worker');
    $item = $queue->claimItem(1);

    if (!$item) {
      return;
    }

    try {
      $queueWorker->processItem($item->data);
      $queue->deleteItem($item);
    }
    catch (\Exception $e) {
      $queue->releaseItem($item);

      throw $e;
    }
  }

  /**
   * Queues the given item.
   *
   * @param string $status
   *   The expected status.
   *
   * @return \Drupal\commerce_order\Entity\OrderInterface
   *   The order.
   */
  private function createQueueItem(string $status) : OrderInterface {
    $builder = $this->prophesize(PaymentRequestBuilderInterface::class);
    $builder
      ->get(Argument::any(), Argument::any())
      ->willReturn(
        (new Payment())
          ->setStatus($status)
          ->setTransactionId('123')
      );
    $this->getGatewayPluginForBuilder($builder->reveal());
    $order = $this->createOrder();
    /** @var \Drupal\Core\Queue\QueueInterface $queue */
    $queue = $this->container->get('queue')
      ->get('commerce_paytrail_notification_worker');
    $queue->createItem([
      'transaction_id' => '123',
      'order_id' => $order->id(),
    ]);

    return $this->reloadEntity($order);
  }

  /**
   * Tests that item is released if order is not found.
   *
   * @covers ::create
   * @covers ::processItem
   * @covers ::getPaymentPlugin
   * @covers ::__construct
   */
  public function testNoOrderFound() : void {
    $order = $this->createQueueItem(Payment::STATUS_OK);
    $order->delete();
    static::assertEquals(1, $this->getQueue()->numberOfItems());
    $this->claimQueueItem();
    static::assertEquals(0, $this->getQueue()->numberOfItems());
  }

  /**
   * Tests that item is released if the order is paid already.
   *
   * @covers ::create
   * @covers ::processItem
   * @covers ::getPaymentPlugin
   * @covers ::__construct
   */
  public function testNotifyOrderIsPaidAlready() : void {
    $order = $this->createQueueItem(Payment::STATUS_OK);
    static::assertEquals(1, $this->getQueue()->numberOfItems());
    $order->setTotalPaid($order->getTotalPrice());
    $order->getState()->applyTransitionById('place');
    $order->save();
    $this->claimQueueItem();

    $order = $this->reloadEntity($order);
    $this->assertTrue($order->isPaid());
    static::assertEquals(0, $this->getQueue()->numberOfItems());
  }

  /**
   * Tests that pending payment state will throw and exception.
   *
   * @covers ::create
   * @covers ::processItem
   * @covers ::getPaymentPlugin
   * @covers ::__construct
   */
  public function testOnNotifyPendingOrder() : void {
    $order = $this->createQueueItem(Payment::STATUS_PENDING);
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('Order payment is not completed for order: ' . $order->id());
    $this->claimQueueItem();
  }

  /**
   * Tests that items are released from queue after N number of tries.
   *
   * @covers ::create
   * @covers ::processItem
   * @covers ::getPaymentPlugin
   * @covers ::__construct
   */
  public function testQueueRelease() : void {
    $numExceptions = 0;
    $order = $this->createQueueItem(Payment::STATUS_PENDING);

    for ($i = 0; $i <= NotificationWorker::NUM_MAX_TRIES; $i++) {
      try {
        $this->claimQueueItem();
      }
      catch (\InvalidArgumentException) {
        $numExceptions++;
      }
      $order = $this->reloadEntity($order);
      static::assertEquals($numExceptions, $order->getData(NotificationWorker::MAX_TRIES_SETTING));
    }
    $this->claimQueueItem();
    // Make sure order is released from queue for good once we reach the
    // maximum tries.
    static::assertEquals(0, $this->getQueue()->numberOfItems());
  }

  /**
   * Make sure payment gets paid.
   *
   * @covers ::create
   * @covers ::processItem
   * @covers ::getPaymentPlugin
   * @covers ::__construct
   */
  public function testSuccessfulNotify() : void {
    $this->createQueueItem(Payment::STATUS_OK);
    $this->claimQueueItem();

    $payment = $this->loadPayment('123');
    static::assertEquals('completed', $payment->getState()->getId());
    static::assertEquals(Payment::STATUS_OK, $payment->getRemoteState());
  }

}
