<?php

declare(strict_types = 1);

namespace Drupal\commerce_paytrail\Repository;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_paytrail\Exception\InvalidValueException;
use Drupal\commerce_paytrail\Exception\SecurityHashMismatchException;
use Symfony\Component\HttpFoundation\Request;
use Webmozart\Assert\Assert;

/**
 * Defines the data type for paytrail response.
 */
class Response extends BaseResource {

  protected $order;
  protected $orderNumber;
  protected $paymetMethod;
  protected $paymentId;
  protected $redirectKey;
  protected $remoteId;
  protected $authCode;
  protected $status;
  protected $timestamp;

  public function __construct(string $merchantHash, OrderInterface $order) {
    $this->order = $order;

    parent::__construct($merchantHash);
  }

  public function setOrderNumber(string $orderNumber) : self {
    $this->orderNumber = $orderNumber;
    return $this;
  }

  public function setAuthCode(string $code) : self {
    $this->authCode = $code;
    return $this;
  }

  public function setPaymentMethod(string $method) : self {
    $this->paymetMethod = $method;
    return $this;
  }

  public function setTimestamp(int $timestamp) : self {
    $this->timestamp = $timestamp;
    return $this;
  }

  public static function createFromRequest(string $merchantHash, OrderInterface $order, Request $request) : self {
    $required = [
      'ORDER_NUMBER',
      'PAYMENT_ID',
      'PAYMENT_METHOD',
      'TIMESTAMP',
      'STATUS',
      'RETURN_AUTHCODE',
    ];

    foreach ($required as $key) {
      if (!$value = $request->query->get($key)) {
        throw new InvalidValueException(sprintf('Value for %s not found', $key));
      }
    }
    return (new static($merchantHash, $order))
      ->setAuthCode($request->query->get('RETURN_AUTHCODE'))
      ->setOrderNumber($request->query->get('ORDER_NUMBER'))
      ->setPaymentId($request->query->get('PAYMENT_ID'))
      ->setPaymentMethod($request->query->get('PAYMENT_METHOD'))
      ->setTimestamp((int) $request->query->get('TIMESTAMP'))
      ->setPaymentStatus($request->query->get('STATUS'));
  }

  protected function setPaymentStatus(string $status) : self {
    Assert::oneOf($status, ['PAID', 'CANCELLED']);
    $this->status = $status;

    return $this;
  }

  public function setPaymentId(string $paymentId) : self {
    $this->paymentId = $paymentId;
    return $this;
  }

  public function getPaymentStatus() : string {
    return $this->status;
  }

  public function getAuthCode() : string {
    return $this->authCode;
  }

  public function getPaymentMethod() : string {
    return $this->paymetMethod;
  }

  public function getOrderNumber() : string {
    return $this->orderNumber;
  }

  public function getRedirectKey() : string {
    return $this->redirectKey;
  }

  public function getOrder() : OrderInterface {
    return $this->order;
  }

  public function getPaymentId() : string {
    return $this->paymentId;
  }

  public function getTimestamp() : int {
    return $this->timestamp;
  }

  public function isValidResponse() : void {
    $hash_values = [
      $this->getOrderNumber(),
      $this->getPaymentId(),
      $this->getPaymentMethod(),
      $this->getTimestamp(),
      $this->getPaymentStatus(),
    ];

    // Make sure payment status is paid.
    if ($this->getPaymentStatus() !== 'PAID') {
      throw new SecurityHashMismatchException('Validation failed (invalid paymetn state)');
    }

    // Make sure we have a valid order number and it matches the one given
    // to the Paytrail.
    if ((string) $this->order->id() !== $this->getOrderNumber()) {
      throw new SecurityHashMismatchException('Validation failed (order number mismatch)');
    }

    if ($this->generateReturnChecksum($hash_values) !== $this->getAuthCode()) {
      throw new SecurityHashMismatchException('Validation failed (security hash mismatch)');
    }
  }

}
