<?php

namespace Drupal\commerce_paytrail;

use Drupal\address\AddressInterface;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_payment\Exception\PaymentGatewayException;
use Drupal\commerce_paytrail\Event\PaytrailEvents;
use Drupal\commerce_paytrail\Event\TransactionRepositoryEvent;
use Drupal\commerce_paytrail\Exception\InvalidBillingException;
use Drupal\commerce_paytrail\Plugin\Commerce\PaymentGateway\PaytrailBase;
use Drupal\commerce_paytrail\Repository\E1TransactionRepository;
use Drupal\commerce_paytrail\Repository\MethodRepository;
use Drupal\commerce_paytrail\Repository\PaytrailProduct;
use Drupal\commerce_paytrail\Repository\S1TransactionRepository;
use Drupal\Component\Datetime\TimeInterface;
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
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManager $entity_type_manager
   *   The entity type manager service.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The event dispatcher.
   * @param \Drupal\commerce_paytrail\Repository\MethodRepository $method_repository
   *   The payment method repository.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The current time.
   */
  public function __construct(EntityTypeManager $entity_type_manager, EventDispatcherInterface $event_dispatcher, MethodRepository $method_repository, TimeInterface $time) {
    $this->entityTypeManager = $entity_type_manager;
    $this->eventDispatcher = $event_dispatcher;
    $this->methodRepository = $method_repository;
    $this->time = $time;
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
  public function getReturnUrl(OrderInterface $order, $type, $step = 'payment') {
    $arguments = [
      'commerce_order' => $order->id(),
      'step' => $step,
      'commerce_payment_gateway' => 'paytrail',
    ];
    $url = new Url($type, $arguments, [
      'absolute' => TRUE,
      'query' => ['redirect_key' => $this->getRedirectKey($order)],
    ]);

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
    $redirect_key = Crypt::hmacBase64(sprintf('%s:%s', $order->id(), $this->time->getRequestTime()), $uuid->generate());

    $order->setData('paytrail_redirect_key', $redirect_key);
    $order->save();

    return $redirect_key;
  }

  /**
   * {@inheritdoc}
   */
  public function buildTransaction(OrderInterface $order, PaytrailBase $plugin, $preselected_method = NULL) {
    $type = $plugin->getSetting('paytrail_type');

    $repository = $type === 'S1' ? new S1TransactionRepository() : new E1TransactionRepository();

    if ($repository instanceof S1TransactionRepository) {
      $repository->setAmount($order->getTotalPrice());
    }
    else {
      $billing_data = $order->getBillingProfile()->get('address')->first();

      // Billing data is required for this.
      if (!$billing_data instanceof AddressInterface) {
        throw new InvalidBillingException('Invalid billing data for ' . $order->id());
      }
      $repository->setContactEmail($order->getEmail())
        ->setBillingProfile($billing_data)
        // @todo Check commerce settings.
        ->setIncludeVat(1)
        ->setItems(count($order->getItems()));

      foreach ($order->getItems() as $delta => $item) {
        // @todo Implement taxes when commerce_tax is available.
        // @todo Implement discount and item type handling.
        $product = PaytrailProduct::createWithOrderItem($item);
        $repository->setProduct($delta, $product);
      }
    }
    $repository->setOrderNumber($order->id())
      ->setReturnAddress($this->getReturnUrl($order, 'commerce_payment.checkout.return'))
      ->setCancelAddress($this->getReturnUrl($order, 'commerce_payment.checkout.cancel'))
      ->setPendingAddress($this->getReturnUrl($order, 'commerce_payment.checkout.return'))
      ->setNotifyAddress($this->getReturnUrl($order, 'commerce_payment.notify'))
      ->setMerchantId($plugin->getMerchantId())
      // Preselected method will be populated with ajax.
      ->setPreselectedMethod($preselected_method)
      ->setCulture($plugin->getCulture())
      // Use bypass mode if preselected method is available. It should not be
      // possible to have preselected method without using bypass mode.
      ->setMode($preselected_method ? PaytrailBase::BYPASS_MODE : PaytrailBase::NORMAL_MODE)
      ->setVisibleMethods($plugin->getSetting('visible_methods'));

    $repository_alter = new TransactionRepositoryEvent($plugin, clone $order, $repository);
    // Allow element values to be altered.
    /** @var \Drupal\commerce_paytrail\Event\TransactionRepositoryEvent $event */
    $event = $this->eventDispatcher->dispatch(PaytrailEvents::TRANSACTION_REPO_ALTER, $repository_alter);
    // Build repository array.
    $values = $event->getTransactionRepository()->build();
    // Generate authcode based on values submitted.
    $values['AUTHCODE'] = $this->generateAuthCode($plugin->getMerchantHash(), $values);

    return $values;
  }

  /**
   * {@inheritdoc}
   */
  protected function getPayment(OrderInterface $order, PaytrailBase $plugin) {
    /** @var \Drupal\commerce_payment\Entity\PaymentInterface[] $payments */
    $payments = $this->entityTypeManager
      ->getStorage('commerce_payment')
      ->loadByProperties(['order_id' => $order->id()]);

    if (empty($payments)) {
      return FALSE;
    }
    foreach ($payments as $payment) {
      if ($payment->getPaymentGatewayId() !== $plugin->getEntityId() || $payment->getAmount()->compareTo($order->getTotalPrice()) !== 0) {
        continue;
      }
      $paytrail_payment = $payment;
    }
    return empty($paytrail_payment) ? FALSE : $paytrail_payment;
  }

  /**
   * {@inheritdoc}
   */
  public function createPaymentForOrder($status, OrderInterface $order, PaytrailBase $plugin, array $remote) {
    $payment_storage = $this->entityTypeManager->getStorage('commerce_payment');

    if (!$payment = $this->getPayment($order, $plugin)) {
      /** @var \Drupal\commerce_payment\Entity\PaymentInterface $payment */
      $payment = $payment_storage->create([
        'amount' => $order->getTotalPrice(),
        'payment_gateway' => $plugin->getEntityId(),
        'order_id' => $order->id(),
        'test' => $plugin->getMode() == 'test',
      ]);
    }
    else {
      // Make sure remote id does not change.
      if ($remote['remote_id'] !== $payment->getRemoteId()) {
        throw new PaymentGatewayException('Remote id does not match with previously stored remote id.');
      }
    }
    $payment->setRemoteId($remote['remote_id'])
      ->setRemoteState($remote['remote_state']);

    if ($status === 'authorized') {
      $transition = $payment->getState()->getWorkflow()->getTransition('authorize');
      $payment->getState()->applyTransition($transition);
    }
    elseif ($status === 'capture') {
      if ($payment->getState()->value != 'authorization') {
        throw new \InvalidArgumentException('Only payments in the "authorization" state can be captured.');
      }
      $capture_transition = $payment->getState()->getWorkflow()->getTransition('capture');
      $payment->getState()->applyTransition($capture_transition);
    }
    $payment->save();

    return $payment;
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
