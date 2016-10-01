<?php

namespace Drupal\commerce_paytrail\Controller;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\Core\Controller\ControllerBase;

/**
 * Class PaymentController.
 *
 * @package Drupal\commerce_paytrail\Controller
 */
class PaymentController extends ControllerBase {

  /**
   * Callback to initialize payment chain.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order.
   * @param string $redirect_key
   *   The redirect key.
   */
  public function initialize(OrderInterface $order, $redirect_key) {}

  /**
   * Callback after succesful payment.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order.
   * @param string $redirect_key
   *   The redirect key.
   * @param string $type
   *   Return method (cancel, notify or success).
   */
  public function returnTo(OrderInterface $order, $redirect_key, $type) {
    if ($type === 'cancel') {
      return $this->cancel($order, $redirect_key);
    }
    elseif ($type === 'notify') {
      return $this->notify($order, $redirect_key);
    }
  }

  /**
   * Callback after succesful payment.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order.
   * @param string $redirect_key
   *   The redirect key.
   */
  public function cancel(OrderInterface $order, $redirect_key) {}

  /**
   * Callback after succesful payment.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order.
   * @param string $redirect_key
   *   The redirect key.
   */
  public function notify(OrderInterface $order, $redirect_key) {}

  /**
   * Check if user has access to callback.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order.
   * @param string $redirect_key
   *   The redirect key.
   *
   * @return mixed
   *   TRUE if has access, FALSE if does not.
   */
  public function access(OrderInterface $order, $redirect_key) {
    $correct_redirect_key = $this->paymentManager->getRedirectKey($order);
    $is_allowed = !empty($redirect_key) && $correct_redirect_key == $redirect_key && $order->getOwnerId() == $this->currentUser()->id();

    return AccessResult::allowedIf($is_allowed);
  }

}
