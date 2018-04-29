<?php

declare(strict_types = 1);

namespace Drupal\commerce_paytrail;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_payment\Entity\PaymentInterface;
use Drupal\commerce_payment\Exception\PaymentGatewayException;
use Drupal\commerce_paytrail\Event\PaymentEvent;
use Drupal\commerce_paytrail\Event\PaytrailEvents;
use Drupal\commerce_paytrail\Event\FormInterfaceEvent;
use Drupal\commerce_paytrail\Plugin\Commerce\PaymentGateway\PaytrailBase;
use Drupal\commerce_paytrail\Repository\FormManager;
use Drupal\commerce_paytrail\Repository\Response;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Url;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Provides shared payment related functionality.
 */
class PaymentManager implements PaymentManagerInterface {

  /**
   * Drupal\Core\Entity\EntityTypeManager definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * Constructs a new instance.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The event dispatcher.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The current time.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, EventDispatcherInterface $event_dispatcher, TimeInterface $time) {
    $this->entityTypeManager = $entity_type_manager;
    $this->eventDispatcher = $event_dispatcher;
    $this->time = $time;
  }

  /**
   * {@inheritdoc}
   */
  public function getReturnUrl(OrderInterface $order, string $type, $step = 'payment') : string {
    $arguments = [
      'commerce_order' => $order->id(),
      'step' => $step,
      'commerce_payment_gateway' => 'paytrail',
    ];

    return (new Url($type, $arguments, ['absolute' => TRUE]))
      ->toString();
  }

  /**
   * {@inheritdoc}
   */
  public function buildFormInterface(OrderInterface $order, PaytrailBase $plugin) : FormManager {
    $form = new FormManager($plugin->getMerchantId(), $plugin->getMerchantHash());

    $form->setOrderNumber($order->id())
      ->setAmount($order->getTotalPrice())
      ->setSuccessUrl($this->getReturnUrl($order, 'commerce_payment.checkout.return'))
      ->setCancelUrl($this->getReturnUrl($order, 'commerce_payment.checkout.cancel'))
      ->setNotifyUrl($this->getReturnUrl($order, 'commerce_payment.notify'))
      ->setPaymentMethods($plugin->getVisibleMethods());

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function dispatch(FormManager $form, PaytrailBase $plugin, OrderInterface $order) : array {
    $form_alter = new FormInterfaceEvent($plugin, clone $order, $form);
    // Allow element values to be altered.
    /** @var \Drupal\commerce_paytrail\Event\FormInterfaceEvent $event */
    $event = $this->eventDispatcher->dispatch(PaytrailEvents::FORM_ALTER, $form_alter);

    $values = $event->getFormInterface()->build();
    // Generate authcode based on values submitted.
    $values['AUTHCODE'] = $form->generateAuthCode($values);

    return $values;
  }

  /**
   * {@inheritdoc}
   */
  protected function getPayment(OrderInterface $order, PaytrailBase $plugin) : ? PaymentInterface {
    /** @var \Drupal\commerce_payment\Entity\PaymentInterface[] $payments */
    $payments = $this->entityTypeManager
      ->getStorage('commerce_payment')
      ->loadByProperties(['order_id' => $order->id()]);

    if (empty($payments)) {
      return NULL;
    }
    $paytrail_payment = NULL;

    foreach ($payments as $payment) {
      if ($payment->getPaymentGatewayId() !== $plugin->getEntityId() || $payment->getAmount()->compareTo($order->getTotalPrice()) !== 0) {
        continue;
      }
      $paytrail_payment = $payment;
    }
    return $paytrail_payment ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function createPaymentForOrder(string $status, OrderInterface $order, PaytrailBase $plugin, Response $response) : PaymentInterface {
    $payment_storage = $this->entityTypeManager->getStorage('commerce_payment');

    if (!$payment = $this->getPayment($order, $plugin)) {
      /** @var \Drupal\commerce_payment\Entity\PaymentInterface $payment */
      $payment = $payment_storage->create([
        'amount' => $order->getTotalPrice(),
        'payment_gateway' => $plugin->getEntityId(),
        'order_id' => $order->id(),
        'test' => $plugin->getMode() == 'test',
      ]);
      $payment->setAuthorizedTime($this->time->getRequestTime());

      // This should only happen when PaytrailBase::onNotify() is trying to
      // call this with 'capture' status when no payment exist yet.
      // That usually happens when user completed the payment, but didn't return
      // from the payment service.
      if ($plugin->ipnAllowedToCreatePayment() && $status === 'capture') {
        // Complete 'authorize' transition to run necessary event subscribers.
        $transition = $payment->getState()->getWorkflow()->getTransition('authorize');
        $payment->getState()->applyTransition($transition);

        /** @var \Drupal\commerce_paytrail\Event\PaymentEvent $event */
        $this->eventDispatcher->dispatch(PaytrailEvents::IPN_CREATED_PAYMENT,
          new PaymentEvent($payment)
        );
      }
    }
    else {
      // Make sure remote id does not change.
      if ($response->getPaymentId() !== $payment->getRemoteId()) {
        throw new PaymentGatewayException('Remote id does not match with previously stored remote id.');
      }
    }

    // Prevent payment state from being overridden when IPN completes the
    // payment before user is able to return from the payment service (due
    // to slow connection for example).
    if ($payment->getState()->value === 'completed') {
      return $payment;
    }
    $payment->setRemoteId($response->getPaymentId())
      ->setRemoteState($response->getPaymentStatus());

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

}
