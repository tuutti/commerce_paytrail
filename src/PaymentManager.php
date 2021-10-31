<?php

declare(strict_types = 1);

namespace Drupal\commerce_paytrail;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_payment\Entity\PaymentInterface;
use Drupal\commerce_payment\Exception\PaymentGatewayException;
use Drupal\commerce_paytrail\Event\PaymentEvent;
use Drupal\commerce_paytrail\Event\PaytrailEvents;
use Drupal\commerce_paytrail\Event\FormInterfaceEvent;
use Drupal\commerce_paytrail\Plugin\Commerce\PaymentGateway\Paytrail;
use Drupal\commerce_paytrail\Repository\FormManager;
use Drupal\commerce_paytrail\Repository\Response;
use Drupal\commerce_paytrail\RequestBuilder\RequestBuilderBase;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Url;
use GuzzleHttp\ClientInterface;
use Paytrail\Payment\Api\PaymentsApi;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Provides shared payment related functionality.
 */
class PaymentManager implements PaymentManagerInterface {

  /**
   * Constructs a new instance.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager service.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $eventDispatcher
   *   The event dispatcher service.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected EventDispatcherInterface $eventDispatcher,
    protected TimeInterface $time,
    protected ClientInterface $client
  ) {
  }

  /**
   * Gets the plugin for given order.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order.
   *
   * @return \Drupal\commerce_paytrail\Plugin\Commerce\PaymentGateway\Paytrail
   *   The payment plugin.
   */
  public function getPlugin(OrderInterface $order) : Paytrail {
    $gateway = $order->get('payment_gateway');

    if ($gateway->isEmpty()) {
      throw new \InvalidArgumentException('Payment gateway not found.');
    }
    $plugin = $gateway->first()->entity->getPlugin();

    if (!$plugin instanceof Paytrail) {
      throw new PaytrailPluginException('Payment gateway not instanceof Klarna.');
    }
    return $plugin;
  }

  /**
   * Gets the payments api object.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order.
   *
   * @return \Paytrail\Payment\Api\PaymentsApi
   *   The payments api.
   *
   * @throws \Drupal\commerce_paytrail\PaytrailPluginException
   */
  public function getPaymentsApi(OrderInterface $order) : PaymentsApi {
    return new PaymentsApi($this->client, $this->getPlugin($order)->getClientConfiguration());
  }

  public function getPaymentProviders(OrderInterface $order) {
    $api = $this->getPaymentsApi($order);
    $clientConfiguration = $this->getPlugin($order)->getClientConfiguration();
    $request = new RequestBuilderBase(\Drupal::service('uuid'));
    $headers = $request->getDefaultHeaders($clientConfiguration);

    return $api->getPaymentProviders($headers['checkout-account'], $headers['checkout-algorithm'], $headers['checkout-method'], $headers['checkout-timestamp'], $headers['checkout-nonce'], $request->signature(), $order->getTotalPrice()->getNumber());
  }

  /**
   * Builds the return url.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order.
   * @param string $type
   *   The return url type.
   * @param array $arguments
   *   The additional arguments.
   *
   * @return string
   *   The return url.
   */
  private function buildReturnUrl(OrderInterface $order, string $type, array $arguments = []) : string {
    $arguments = array_merge([
      'commerce_order' => $order->id(),
      'step' => $arguments['step'] ?? 'payment',
    ], $arguments);

    return (new Url($type, $arguments, ['absolute' => TRUE]))
      ->toString();
  }

  /**
   * {@inheritdoc}
   */
  public function buildFormInterface(OrderInterface $order) : FormManager {
    $form->setOrderNumber($order->id())
      ->setAmount($order->getBalance())
      ->setLocale($plugin->getCulture())
      ->setSuccessUrl($this->buildReturnUrl($order, 'commerce_payment.checkout.return'))
      ->setCancelUrl($this->buildReturnUrl($order, 'commerce_payment.checkout.cancel'))
      ->setNotifyUrl($this->buildReturnUrl($order, 'commerce_payment.notify', [
        'commerce_payment_gateway' => $plugin->getEntityId(),
      ]))
      ->setPaymentMethods($plugin->getVisibleMethods());

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function dispatch(FormManager $form, Paytrail $plugin, OrderInterface $order) : array {
    $form_alter = new FormInterfaceEvent($plugin, clone $order, $form);
    // Allow element values to be altered.
    /** @var \Drupal\commerce_paytrail\Event\FormInterfaceEvent $event */
    $event = $this->eventDispatcher->dispatch(PaytrailEvents::FORM_ALTER, $form_alter);

    $values = $event->getFormInterface()->build();
    // Generate authcode based on submitted values.
    $values['AUTHCODE'] = $form->generateAuthCode($values);

    return $values;
  }

  /**
   * {@inheritdoc}
   */
  protected function getPayment(OrderInterface $order, Paytrail $plugin) : ? PaymentInterface {
    /** @var \Drupal\commerce_payment\Entity\PaymentInterface[] $payments */
    $payments = $this->entityTypeManager->getStorage('commerce_payment')
      ->loadMultipleByOrder($order);

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
  public function createPaymentForOrder(string $status, OrderInterface $order, Paytrail $plugin, Response $response) : PaymentInterface {
    if (!$payment = $this->getPayment($order, $plugin)) {
      /** @var \Drupal\commerce_payment\Entity\PaymentInterface $payment */
      $payment = $this->entityTypeManager->getStorage('commerce_payment')->create([
        'amount' => $order->getTotalPrice(),
        'payment_gateway' => $plugin->getEntityId(),
        'order_id' => $order->id(),
        'test' => $plugin->getMode() == 'test',
      ]);
      $payment->setAuthorizedTime($this->time->getRequestTime())
        ->setRemoteId($response->getPaymentId());

      // This should only happen when PaytrailBase::onNotify() is trying to
      // call this with 'capture' status when no payment exist yet.
      // That usually happens when user completed the payment, but didn't return
      // from the payment service.
      if ($plugin->ipnAllowedToCreatePayment() && $status === 'capture') {
        // Complete 'authorize' transition to run necessary event subscribers.
        $payment->getState()->applyTransitionById('authorize');

        /** @var \Drupal\commerce_paytrail\Event\PaymentEvent $event */
        $this->eventDispatcher->dispatch(PaytrailEvents::IPN_CREATED_PAYMENT,
          new PaymentEvent($payment)
        );
      }
    }

    // Make sure remote id does not change.
    if ($response->getPaymentId() !== $payment->getRemoteId()) {
      throw new PaymentGatewayException('Remote id does not match with previously stored remote id.');
    }

    // Prevent payment state from being overridden if IPN completes the
    // payment before user is returned from the payment service (due
    // to slow connection for example).
    if ($payment->getState()->value === 'completed') {
      return $payment;
    }
    $payment->setRemoteId($response->getPaymentId())
      ->setRemoteState($response->getPaymentStatus());

    if ($status === 'authorized') {
      $payment->getState()->applyTransitionById('authorize');
    }
    elseif ($status === 'capture') {
      if ($payment->getState()->value != 'authorization') {
        throw new \InvalidArgumentException('Only payments in the "authorization" state can be captured.');
      }
      $payment->getState()->applyTransitionById('capture');
    }
    $payment->save();

    return $payment;
  }

}
