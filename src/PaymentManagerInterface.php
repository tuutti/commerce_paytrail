<?php

namespace Drupal\commerce_paytrail;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_paytrail\Plugin\Commerce\PaymentGateway\PaytrailBase;

/**
 * Interface PaymentManagerInterface.
 *
 * @package Drupal\commerce_paytrail
 */
interface PaymentManagerInterface {

  /**
   * Get available payment methods.
   *
   * @param array $enabled
   *   List of enabled payment methods.
   *
   * @return array|mixed
   *   List of available payment methods.
   */
  public function getPaymentMethods(array $enabled = []);

  /**
   * Get return url for given type.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   Order.
   * @param string $type
   *   Return type.
   *
   * @return \Drupal\Core\GeneratedUrl|string
   *   Return absolute return url.
   */
  public function getReturnUrl(OrderInterface $order, $type);

  /**
   * Get/generate payment redirect key.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   Order.
   *
   * @return string
   *   Payment redirect key.
   */
  public function getRedirectKey(OrderInterface $order);

  /**
   * Build transaction for order.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   Order.
   * @param \Drupal\commerce_paytrail\Plugin\Commerce\PaymentGateway\PaytrailBase $payment_gateway
   *   The payment gateway.
   * @param int $preselected_method
   *   The optional preselected method.
   *
   * @return array|bool
   *   FALSE on validation failure or transaction array.
   */
  public function buildTransaction(OrderInterface $order, PaytrailBase $payment_gateway, $preselected_method = NULL);

  /**
   * Create new payment for given order.
   *
   * @param string $status
   *   The transaction state (authorized, capture).
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order.
   * @param \Drupal\commerce_paytrail\Plugin\Commerce\PaymentGateway\PaytrailBase $plugin
   *   The payment plugin.
   * @param array $remote
   *   The remove values.
   *
   * @return \Drupal\commerce_payment\Entity\PaymentInterface
   *   The payment entity.
   */
  public function createPaymentForOrder($status, OrderInterface $order, PaytrailBase $plugin, array $remote);

  /**
   * Calculate authcode for transaction.
   *
   * @param string $hash
   *   Merchant hash.
   * @param array $values
   *   Values used to generate mac.
   *
   * @return string
   *   Authcode hash.
   */
  public function generateAuthCode($hash, array $values);

  /**
   * Calculate return checksum.
   *
   * @param string $hash
   *   Merchant hash.
   * @param array $values
   *   Values used to generate mac.
   *
   * @return string
   *   Checksum.
   */
  public function generateReturnChecksum($hash, array $values);

}
