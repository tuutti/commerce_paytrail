<?php

namespace Drupal\commerce_paytrail\Event;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_paytrail\Plugin\Commerce\PaymentGateway\Paytrail;
use Drupal\commerce_paytrail\Repository\TransactionRepository;
use Symfony\Component\EventDispatcher\Event;

/**
 * Class TransactionRepositoryEvent.
 *
 * @package Drupal\commerce_paytrail\Event\PaymentReposityEvent
 */
class TransactionRepositoryEvent extends Event {

  /**
   * The Paytrail payment plugin.
   *
   * @var \Drupal\commerce_paytrail\Plugin\Commerce\PaymentGateway\Paytrail
   */
  protected $plugin;

  /**
   * The commerce order.
   *
   * @var \Drupal\commerce_order\Entity\OrderInterface
   */
  protected $order;

  /**
   * The transaction repository.
   *
   * @var \Drupal\commerce_paytrail\Repository\TransactionRepository
   */
  protected $repository;

  /**
   * TransactionRepositoryEvent constructor.
   *
   * @param \Drupal\commerce_paytrail\Plugin\Commerce\PaymentGateway\Paytrail $plugin
   *   The Paytrail payment plugin.
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order.
   * @param \Drupal\commerce_paytrail\Repository\TransactionRepository $repository
   *   The transaction repository.
   */
  public function __construct(Paytrail $plugin, OrderInterface $order, TransactionRepository $repository) {
    $this->plugin = $plugin;
    $this->order = $order;
    $this->repository = $repository;
  }

  /**
   * Set order.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order.
   *
   * @return $this
   */
  public function setOrder(OrderInterface $order) {
    $this->order = $order;
    return $this;
  }

  /**
   * Set transaction repository.
   *
   * @param \Drupal\commerce_paytrail\Repository\TransactionRepository $repository
   *   The transaction repository.
   *
   * @return $this
   */
  public function setTransactionRepository(TransactionRepository $repository) {
    $this->repository = $repository;
    return $this;
  }

  /**
   * Get transaction repository.
   *
   * @return \Drupal\commerce_paytrail\Repository\TransactionRepository
   *   The transaction repository.
   */
  public function getTransactionRepository() {
    return $this->repository;
  }

  /**
   * Get clone of commerce order.
   *
   * @return \Drupal\commerce_order\Entity\OrderInterface
   *   The order.
   */
  public function getOrder() {
    return $this->order;
  }

  /**
   * Get payment plugin.
   *
   * @return \Drupal\commerce_paytrail\Plugin\Commerce\PaymentGateway\Paytrail
   *   The paytrail payment plugin.
   */
  public function getPlugin() {
    return $this->plugin;
  }

}
