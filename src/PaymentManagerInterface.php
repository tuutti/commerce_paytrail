<?php

namespace Drupal\commerce_paytrail;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_payment\Entity\PaymentInterface;

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
   *
   * @return array|bool
   *   FALSE on validation failure or transaction array.
   */
  public function buildTransaction(OrderInterface $order);

  /**
   * Attempt to fetch payment for given order.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order.
   *
   * @return bool|\Drupal\commerce_payment\Entity\PaymentInterface
   *   Payment object on success, FALSE on failure.
   */
  public function getPayment(OrderInterface $order);

  /**
   * Create payment entity for given order.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The Order.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   The payment entity.
   */
  public function buildPayment(OrderInterface $order);

  /**
   * Complete payment.
   *
   * @param \Drupal\commerce_payment\Entity\PaymentInterface $payment
   *   The payment.
   * @param string $status
   *   Payment status. Available statuses: cancel, failed, success.
   *
   * @return bool
   *   Status of payment.
   */
  public function completePayment(PaymentInterface $payment, $status);

  /**
   * Complete commerce order.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order.
   */
  public function completeOrder(OrderInterface $order);

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
