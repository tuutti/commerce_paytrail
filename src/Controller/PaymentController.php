<?php

namespace Drupal\commerce_paytrail\Controller;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_paytrail\PaymentManagerInterface;
use Drupal\commerce_paytrail\Plugin\Commerce\PaymentGateway\Paytrail;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class PaymentController.
 *
 * @package Drupal\commerce_paytrail\Controller
 */
class PaymentController extends ControllerBase {

  /**
   * The payment manager.
   *
   * @var \Drupal\commerce_paytrail\PaymentManagerInterface
   */
  protected $paymentManager;

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * PaymentController constructor.
   *
   * @param \Drupal\commerce_paytrail\PaymentManagerInterface $payment_manager
   *   The payment manager.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The event dispatcher.
   */
  public function __construct(PaymentManagerInterface $payment_manager, EventDispatcherInterface $event_dispatcher) {
    $this->paymentManager = $payment_manager;
    $this->eventDispatcher = $event_dispatcher;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('commerce_paytrail.payment_manager'),
      $container->get('event_dispatcher')
    );
  }

  /**
   * Callback after succesful payment.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   * @param \Drupal\commerce_order\Entity\OrderInterface $commerce_order
   *   The order.
   * @param string $paytrail_redirect_key
   *   The redirect key.
   * @param string $type
   *   Return method (cancel, notify or success).
   *
   * @return array
   *   Render array.
   */
  public function returnTo(Request $request, OrderInterface $commerce_order, $paytrail_redirect_key, $type) {
    $payment_gateway = $commerce_order->get('payment_gateway')->entity;
    $plugin = $payment_gateway->getPlugin();

    if (!$plugin instanceof Paytrail) {
      throw new \InvalidArgumentException('Payment gateway not instance of Paytrail.');
    }
    /** @var \Drupal\commerce_payment\Entity\Payment $payment */
    $payment = $this->paymentManager->getPayment($commerce_order);

    if (!$payment) {
      return $this->errorMessage($this->t('No payment found for this order.'));
    }
    if ($type === 'cancel') {
      $this->paymentManager->completePayment($payment, 'cancel');

      return $this->redirect('commerce_checkout.form', [
        'commerce_order' => $commerce_order->id(),
        // Step will be determined automatically.
        'step' => NULL,
      ]);
    }
    // Payment has been processed already.
    if ($payment->getState()->value != 'new') {
      drupal_set_message($this->t('Payment has already processed (%state).', [
        '%state' => $payment->getState()->getValue(),
      ]), 'warning');
      return $this->redirect('commerce_checkout.form', [
        'commerce_order' => $commerce_order->id(),
        // Step will be determined automatically.
        'step' => NULL,
      ]);
    }
    // Handle return and notify.
    $hash_values = [];
    foreach (['ORDER_NUMBER', 'TIMESTAMP', 'PAID', 'METHOD'] as $key) {
      if (!$value = $request->query->get($key)) {
        continue;
      }
      $hash_values[] = $value;
    }
    $hash = $this->paymentManager->generateReturnChecksum($plugin->getSetting('merchant_hash'), $hash_values);

    // Check checksum validity.
    if ($hash !== $request->query->get('RETURN_AUTHCODE')) {
      return $this->errorMessage($this->t('Validation failed (security hash mismatch). Please contact store administration if the problem persists.'));
    }
    // Complete order after succesful payment.
    if ($this->paymentManager->completePayment($payment, 'success')) {
      // @todo This is repeating the logic from commerce_checkout.
      // Implement better way to do this once commerce provides api for this.
      $this->paymentManager->completeOrder($commerce_order);
    }
    // Redirect to complete order page.
    return $this->redirect('commerce_checkout.form', [
      'commerce_order' => $commerce_order->id(),
    ]);
  }

  /**
   * Return array for errors.
   *
   * @param string $message
   *   Message to show.
   *
   * @return array
   *   Render array.
   */
  public function errorMessage($message) {
    return [
      '#cache' => [
        'max-age' => 0,
      ],
      '#markup' => $message,
    ];
  }

  /**
   * Check if user has access to callback.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $commerce_order
   *   The order.
   * @param string $paytrail_redirect_key
   *   The redirect key.
   * @param string $type
   *   Return type.
   *
   * @return mixed
   *   TRUE if has access, FALSE if does not.
   */
  public function access(OrderInterface $commerce_order, $paytrail_redirect_key, $type) {
    $redirect_key_match = $this->paymentManager->getRedirectKey($commerce_order) === $paytrail_redirect_key;
    $owner_match = $this->currentUser()->id() === $commerce_order->getCustomerId();

    return AccessResult::allowedIf($redirect_key_match && $owner_match);
  }

}
