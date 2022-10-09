<?php

declare(strict_types = 1);

namespace Drupal\commerce_paytrail\Plugin\QueueWorker;

use Drupal\commerce_order\OrderStorage;
use Drupal\commerce_paytrail\Exception\PaytrailPluginException;
use Drupal\commerce_paytrail\PaymentGatewayPluginTrait;
use Drupal\commerce_paytrail\RequestBuilder\PaymentRequestBuilder;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Paytrail\Payment\Model\Payment;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines 'commerce_paytrail_notification_worker' queue worker.
 *
 * @QueueWorker(
 *   id = "commerce_paytrail_notification_worker",
 *   title = @Translation("Notification worker"),
 *   cron = {"time" = 60}
 * )
 */
final class NotificationWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  use PaymentGatewayPluginTrait;

  public const NUM_MAX_TRIES = 10;
  public const MAX_TRIES_SETTING = 'commerce_paytrail_maximum_captures';

  /**
   * Constructs a new instance.
   *
   * @param array $configuration
   *   The configuration.
   * @param string $plugin_id
   *   The plugin id.
   * @param array $plugin_definition
   *   The plugin definition.
   * @param \Drupal\commerce_order\OrderStorage $orderStorage
   *   The order storage.
   * @param \Drupal\commerce_paytrail\RequestBuilder\PaymentRequestBuilder $paymentRequest
   *   The request builder.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger.
   */
  public function __construct(
    array $configuration,
    string $plugin_id,
    array $plugin_definition,
    private OrderStorage $orderStorage,
    private PaymentRequestBuilder $paymentRequest,
    private LoggerInterface $logger
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition
  ) : self {
    return new self(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager')->getStorage('commerce_order'),
      $container->get('commerce_paytrail.payment_request'),
      $container->get('logger.channel.commerce_paytrail')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data) : void {
    ['order_id' => $id, 'transaction_id' => $transactionId] = $data;

    // Order not found or is paid already. We can safely ignore the item.
    if ((!$order = $this->orderStorage->load($id)) || $order->isPaid()) {
      return;
    }

    // The order validation/loading logic changed in 3.0-alpha4 release. Support
    // orders made before 3.0-alpha4 release.
    // @todo Remove this in 4.x.
    if (!$transactionId) {
      $transactionId = $order->getData('commerce_paytrail_transaction_id', NULL);
    }
    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $numTries = $order->getData(self::MAX_TRIES_SETTING, 0);

    // Remove item from the queue when we pass the maximum number of
    // sync attempts.
    if ($numTries >= self::NUM_MAX_TRIES) {
      $this->logger
        ->alert(
          sprintf('[QUEUE]: Payment capture failed too many times for #%s. Giving up ...', $order->id())
        );

      return;
    }

    try {
      $paymentResponse = $this->paymentRequest->get($transactionId, $order);
    }
    catch (PaytrailPluginException) {
      // Nothing to do if dealing with non-paytrail order.
      return;
    }

    try {
      // Re-queue if order is not marked as paid. This should never happen.
      if ($paymentResponse->getStatus() !== Payment::STATUS_OK) {
        throw new \InvalidArgumentException(
          sprintf('Order payment is not completed for order: %s', $id)
        );
      }
      $this
        ->getPaymentPlugin($order)
        ->createPayment($order, $paymentResponse);
    }
    catch (\Exception $e) {
      $order->setData(self::MAX_TRIES_SETTING, ++$numTries)
        ->save();
      // Re-throw exception to force re-queue. This exception will be logged by
      // cron.
      throw $e;
    }
  }

}
