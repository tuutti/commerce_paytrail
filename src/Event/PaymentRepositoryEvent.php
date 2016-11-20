<?php

namespace Drupal\commerce_paytrail\Event;

use Drupal\commerce_paytrail\Repository\Method;
use Symfony\Component\EventDispatcher\Event;

/**
 * Class PaymentRepositoryEvent.
 *
 * @package Drupal\commerce_paytrail\Event
 */
class PaymentRepositoryEvent extends Event {

  /**
   * List of payment methods.
   *
   * @var array
   */
  protected $paymentMethods;

  /**
   * PaymentRepositoryEvent constructor.
   *
   * @param array $payment_methods
   *   Array of payment methods.
   */
  public function __construct(array $payment_methods = []) {
    if (empty($payment_methods)) {
      $this->setPaymentMethods($payment_methods);
    }
  }

  /**
   * Populate multiple payment methods at once.
   *
   * @param array $payment_methods
   *   Array of payment methods.
   *
   * @return $this
   */
  public function setPaymentMethods(array $payment_methods) {
    foreach ($payment_methods as $id => $method) {
      if (!$method instanceof Method) {
        continue;
      }
      $this->paymentMethods[$id] = $method;
    }
    return $this;
  }

  /**
   * Get all payment methods.
   *
   * @return array
   *   All available payment methods.
   */
  public function getPaymentMethods() {
    return $this->paymentMethods;
  }

  /**
   * Set payment method.
   *
   * @param \Drupal\commerce_paytrail\Repository\Method $method
   *   The payment method.
   *
   * @return $this
   */
  public function setPaymentMethod(Method $method) {
    $this->paymentMethods[$method->getId()] = $method;
    return $this;
  }

  /**
   * Get single payment method.
   *
   * @param int $id
   *   Payment id.
   *
   * @return \Drupal\commerce_paytrail\Repository\Method|null
   *   NULL if payment method not found or Method object if available.
   */
  public function getPaymentMethod($id) {
    return isset($this->paymentMethods[$id]) ? $this->paymentMethods[$id] : NULL;
  }

}
