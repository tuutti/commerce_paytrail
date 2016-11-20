<?php

namespace Drupal\commerce_paytrail;

use Drupal\address\AddressInterface;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_payment\Entity\Payment;
use Drupal\commerce_payment\Entity\PaymentInterface;
use Drupal\commerce_paytrail\Event\PaytrailEvents;
use Drupal\commerce_paytrail\Plugin\Commerce\PaymentGateway\Paytrail;
use Drupal\commerce_paytrail\Repository\MethodRepository;
use Drupal\commerce_paytrail\Repository\TransactionRepository;
use Drupal\Component\Utility\Crypt;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Site\Settings;
use Drupal\Core\Url;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\GenericEvent;
use Symfony\Component\Routing\Exception\RouteNotFoundException;

/**
 * Class PaymentManager.
 *
 * @package Drupal\commerce_paytrail
 */
class PaymentManager implements PaymentManagerInterface {

  /**
   * Drupal\Core\Entity\EntityTypeManager definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * The payment method repository.
   *
   * @var \Drupal\commerce_paytrail\Repository\MethodRepository
   */
  protected $repository;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManager $entity_type_manager
   *   The entity type manager service.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The event dispatcher.
   * @param \Drupal\commerce_paytrail\Repository\MethodRepository $repository
   *   The payment method repository.
   */
  public function __construct(EntityTypeManager $entity_type_manager, EventDispatcherInterface $event_dispatcher, MethodRepository $repository) {
    $this->entityTypeManager = $entity_type_manager;
    $this->eventDispatcher = $event_dispatcher;
    $this->repository = $repository;
  }

  /**
   * Get available payment methods.
   *
   * @param array $enabled
   *   List of enabled payment methods.
   *
   * @return array|mixed
   *   List of available payment methods.
   */
  public function getPaymentMethods(array $enabled = []) {
    $methods = $this->repository->getMethods();

    if (empty($enabled)) {
      return $methods;
    }
    return array_intersect_key($methods, array_flip($enabled));
  }

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
  public function getReturnUrl(OrderInterface $order, $type) {
    $arguments = [
      'commerce_order' => $order->id(),
      'paytrail_redirect_key' => $this->getRedirectKey($order),
      'type' => $type,
    ];

    try {
      $url = new Url('commerce_paytrail.return', $arguments, ['absolute' => TRUE]);
      return $url->toString();
    }
    catch (RouteNotFoundException $e) {
      return NULL;
    }
  }

  /**
   * Get/generate payment redirect key.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   Order.
   *
   * @return string
   *   Payment redirect key.
   */
  public function getRedirectKey(OrderInterface $order) {
    // Generate only once.
    if ($redirect_key = $order->getData('paytrail_redirect_key')) {
      return $redirect_key;
    }
    $redirect_key = Crypt::hmacBase64(sprintf('%s:%s', $order->id(), REQUEST_TIME), Settings::getHashSalt());

    $order->setData('paytrail_redirect_key', $redirect_key);
    $order->save();

    return $redirect_key;
  }

  /**
   * Build transaction for order.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   Order.
   *
   * @return array|bool
   *   FALSE on validation failure or transaction array.
   */
  public function buildTransaction(OrderInterface $order) {
    $payment_gateway = $order->payment_gateway->entity;
    $plugin = $payment_gateway->getPlugin();

    if (!$plugin instanceof Paytrail) {
      throw new \InvalidArgumentException('Payment gateway not instance of Paytrail.');
    }
    $repository = new TransactionRepository([
      'MERCHANT_ID' => $plugin->getSetting('merchant_id'),
    ]);
    /** @var \Drupal\commerce_payment\Entity\PaymentMethod $payment_method */
    $payment_method = $order->payment_method->entity;

    $repository->setOrderNumber($order->getOrderNumber())
      ->setReturnAddress('return', $this->getReturnUrl($order, 'return'))
      ->setReturnAddress('cancel', $this->getReturnUrl($order, 'cancel'))
      ->setReturnAddress('pending', $this->getReturnUrl($order, 'pending'))
      ->setReturnAddress('notify', $this->getReturnUrl($order, 'notify'))
      ->setType($plugin->getSetting('paytrail_type'))
      ->setCulture($plugin->getCulture())
      // Attempt to use preselected method if available.
      ->setPreselectedMethod($payment_method->get('preselected_method')->value)
      ->setMode($plugin->getSetting('paytrail_mode'))
      ->setVisibleMethods($plugin->getSetting('visible_methods'));

    if ($plugin->getSetting('paytrail_type') === 'S1') {
      $repository->setAmount($order->getTotalPrice());
    }
    else {
      $billing_data = $order->getBillingProfile()->get('address')->first();

      // Billing data not found.
      if (!$billing_data instanceof AddressInterface) {
        return FALSE;
      }
      $repository->setContactTelno('')
        ->setContactCellno('')
        ->setContactEmail($order->getEmail())
        ->setContactName($billing_data->getGivenName())
        ->setContactCompany($billing_data->getOrganization())
        ->setContactAddress($billing_data->getAddressLine1())
        ->setContactZip($billing_data->getPostalCode())
        ->setContactCity($billing_data->getLocality())
        ->setContactCountry($billing_data->getCountryCode())
        // @todo Check commerce settings.
        ->setIncludeVat(1)
        ->setItems(count($order->getItems()));

      foreach ($order->getItems() as $delta => $item) {
        // @todo Implement: Taxes when commerce_tax is implemneted again.
        // @todo Implement discount and item type handling.
        $repository->setProduct($item);
      }
    }
    $order_clone = clone $order;
    // Allow elements to be altered.
    $event = $this->eventDispatcher->dispatch(PaytrailEvents::TRANSACTION_REPO_ALTER, new GenericEvent(NULL, [
      'plugin' => $plugin,
      'order' => $order_clone,
      'transaction_repository' => $repository,
    ]));
    // Build repository array.
    $values = $event->getArgument('transaction_repository')->build();
    $values['AUTHCODE'] = $this->generateAuthCode($plugin->getSetting('merchant_hash'), $values);

    return $values;
  }

  /**
   * Attempt to fetch payment for given order.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order.
   *
   * @return bool|\Drupal\commerce_payment\Entity\PaymentInterface
   *   Payment object on success, FALSE on failure.
   */
  public function getPayment(OrderInterface $order) {
    /** @var PaymentInterface[] $payments */
    $payments = $this->entityTypeManager
      ->getStorage('commerce_payment')
      ->loadByProperties(['order_id' => $order->id()]);

    if (empty($payments)) {
      return FALSE;
    }
    foreach ($payments as $payment) {
      if ($payment->bundle() !== 'paytrail' || $payment->getAmount()->compareTo($order->getTotalPrice()) !== 0) {
        continue;
      }
      $paytrail_payment = $payment;
    }
    return empty($paytrail_payment) ? FALSE : $paytrail_payment;
  }

  /**
   * Create payment entity for given order.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The Order.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   The payment entity.
   */
  public function buildPayment(OrderInterface $order) {
    // Attempt to get existing payment.
    if ($payment = $this->getPayment($order)) {
      return $payment;
    }
    $payment = Payment::create([
      'type' => 'paytrail',
      'payment_method' => $order->payment_method->target_id,
      'payment_gateway' => $order->payment_gateway->target_id,
      'order_id' => $order->id(),
      'amount' => $order->getTotalPrice(),
      'paytrail_redirect_url' => $this->getRedirectKey($order),
    ]);
    $payment->save();

    return $payment;
  }

  /**
   * Complete payment.
   *
   * @param \Drupal\commerce_payment\Entity\PaymentInterface $payment
   *   The payment.
   * @param string $status
   *   Payment status.
   *
   * @return bool
   *   Status of payment.
   */
  public function completePayment(PaymentInterface $payment, $status) {
    // Payment failed. Delete payment.
    if ($status === PaymentStatus::FAILED) {
      $payment->delete();

      return FALSE;
    }
    elseif ($status === PaymentStatus::SUCCESS) {
      // @todo Is there any reasons to call this rather than directly updating to 'capture' state?
      $transition = $payment->getState()->getWorkflow()->getTransition('authorize');
      $payment->setAuthorizedTime(REQUEST_TIME);
      $payment->getState()->applyTransition($transition);
      $capture_transition = $payment->getState()->getWorkflow()->getTransition('capture');
      $payment->getState()->applyTransition($capture_transition);
      $payment->setCapturedTime(REQUEST_TIME);
      $payment->save();

      return TRUE;
    }
  }

  /**
   * Complete commerce order.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order.
   */
  public function completeOrder(OrderInterface $order) {
    // Place the order.
    $transition = $order->getState()->getWorkflow()->getTransition('place');
    $order->getState()->applyTransition($transition);
    $order->set('checkout_step', 'complete');
    $order->save();
  }

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
  public function generateAuthCode($hash, array $values) {
    return strtoupper(md5($hash . '|' . implode('|', $values)));
  }

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
  public function generateReturnChecksum($hash, array $values) {
    return strtoupper(md5(implode('|', $values) . '|' . $hash));
  }

}
