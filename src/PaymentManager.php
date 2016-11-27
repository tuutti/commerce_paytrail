<?php

namespace Drupal\commerce_paytrail;

use Drupal\address\AddressInterface;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_payment\Entity\PaymentInterface;
use Drupal\commerce_paytrail\Event\PaytrailEvents;
use Drupal\commerce_paytrail\Event\TransactionRepositoryEvent;
use Drupal\commerce_paytrail\Exception\InvalidBillingException;
use Drupal\commerce_paytrail\Plugin\Commerce\PaymentGateway\Paytrail;
use Drupal\commerce_paytrail\Repository\MethodRepository;
use Drupal\commerce_paytrail\Repository\TransactionRepository;
use Drupal\Component\Utility\Crypt;
use Drupal\Component\Uuid\Php;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Url;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

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
  protected $methodRepository;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManager $entity_type_manager
   *   The entity type manager service.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The event dispatcher.
   * @param \Drupal\commerce_paytrail\Repository\MethodRepository $method_repository
   *   The payment method repository.
   */
  public function __construct(EntityTypeManager $entity_type_manager, EventDispatcherInterface $event_dispatcher, MethodRepository $method_repository) {
    $this->entityTypeManager = $entity_type_manager;
    $this->eventDispatcher = $event_dispatcher;
    $this->methodRepository = $method_repository;
  }

  /**
   * {@inheritdoc}
   */
  public function getPaymentMethods(array $enabled = []) {
    $methods = $this->methodRepository->getMethods();

    if (empty($enabled)) {
      return $methods;
    }
    return array_intersect_key($methods, array_flip($enabled));
  }

  /**
   * {@inheritdoc}
   */
  public function getReturnUrl(OrderInterface $order, $type) {
    $arguments = [
      'commerce_order' => $order->id(),
      'paytrail_redirect_key' => $this->getRedirectKey($order),
      'type' => $type,
    ];
    $url = new Url('commerce_paytrail.return', $arguments, ['absolute' => TRUE]);

    return $url->toString();
  }

  /**
   * {@inheritdoc}
   */
  public function getRedirectKey(OrderInterface $order) {
    // Generate only once.
    if ($redirect_key = $order->getData('paytrail_redirect_key')) {
      return $redirect_key;
    }
    $uuid = new Php();
    $redirect_key = Crypt::hmacBase64(sprintf('%s:%s', $order->id(), $this->getTime()), $uuid->generate());

    $order->setData('paytrail_redirect_key', $redirect_key);
    $order->save();

    return $redirect_key;
  }

  /**
   * Gets the current time.
   *
   * @todo Replace with time service in 8.3.x.
   *
   * @return int
   *   The current request time.
   */
  protected function getTime() {
    return (int) $_SERVER['REQUEST_TIME'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildTransaction(OrderInterface $order) {
    $payment_gateway = $order->get('payment_gateway')->entity;
    /** @var \Drupal\commerce_paytrail\Plugin\Commerce\PaymentGateway\Paytrail $plugin */
    $plugin = $payment_gateway->getPlugin();

    if (!$plugin instanceof Paytrail) {
      throw new \InvalidArgumentException('Payment gateway not instance of Paytrail.');
    }
    $repository = new TransactionRepository();
    /** @var \Drupal\commerce_payment\Entity\PaymentMethod $payment_method */
    $payment_method = $order->get('payment_method')->entity;

    $repository->setOrderNumber($order->getOrderNumber())
      ->setMerchantId($plugin->getSetting('merchant_id'));

    foreach (['return', 'cancel', 'pending', 'notify'] as $type) {
      $repository->setReturnAddress($type, $this->getReturnUrl($order, $type));
    }
    $repository->setType($plugin->getSetting('paytrail_type'))
      // EUR is only allowed currency by Paytrail.
      ->setCurrency('EUR')
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
        throw new InvalidBillingException();
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
        // @todo Implement taxes when commerce_tax is available.
        // @todo Implement discount and item type handling.
        $repository->setProduct($item);
      }
    }
    $repository_alter = new TransactionRepositoryEvent($plugin, clone $order, $repository);
    // Allow element values to be altered.
    /** @var TransactionRepositoryEvent $event */
    $event = $this->eventDispatcher->dispatch(PaytrailEvents::TRANSACTION_REPO_ALTER, $repository_alter);
    // Build repository array.
    $values = $event->getTransactionRepository()->build();

    $values['AUTHCODE'] = $this->generateAuthCode($plugin->getSetting('merchant_hash'), $values);

    return $values;
  }

  /**
   * {@inheritdoc}
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
   * {@inheritdoc}
   */
  public function buildPayment(OrderInterface $order) {
    // Attempt to get existing payment.
    if ($payment = $this->getPayment($order)) {
      return $payment;
    }
    $payment = $this->entityTypeManager
      ->getStorage('commerce_payment')
      ->create([
        'type' => 'paytrail',
        'payment_method' => $order->get('payment_method')->target_id,
        'payment_gateway' => $order->get('payment_gateway')->target_id,
        'order_id' => $order->id(),
        'amount' => $order->getTotalPrice(),
      ]);
    $payment->save();

    return $payment;
  }

  /**
   * {@inheritdoc}
   */
  public function completePayment(PaymentInterface $payment, $status) {
    // Payment failed. Delete payment.
    if ($status === 'failed' || $status === 'cancel') {
      $payment->delete();

      return FALSE;
    }
    elseif ($status === 'success') {
      // @todo Is there any reasons to call this rather than directly updating to 'capture' state?
      $transition = $payment->getState()->getWorkflow()->getTransition('authorize');
      $payment->setAuthorizedTime($this->getTime());
      $payment->getState()->applyTransition($transition);
      $capture_transition = $payment->getState()->getWorkflow()->getTransition('capture');
      $payment->getState()->applyTransition($capture_transition);
      $payment->setCapturedTime($this->getTime());
      $payment->save();

      return TRUE;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function completeOrder(OrderInterface $order) {
    // Place the order.
    $transition = $order->getState()->getWorkflow()->getTransition('place');
    $order->getState()->applyTransition($transition);
    $order->set('checkout_step', 'complete');
    $order->save();
  }

  /**
   * {@inheritdoc}
   */
  public function generateAuthCode($hash, array $values) {
    return strtoupper(md5($hash . '|' . implode('|', $values)));
  }

  /**
   * {@inheritdoc}
   */
  public function generateReturnChecksum($hash, array $values) {
    return strtoupper(md5(implode('|', $values) . '|' . $hash));
  }

}
