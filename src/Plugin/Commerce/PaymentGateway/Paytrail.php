<?php

declare(strict_types = 1);

namespace Drupal\commerce_paytrail\Plugin\Commerce\PaymentGateway;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_payment\Entity\PaymentInterface;
use Drupal\commerce_payment\Exception\PaymentGatewayException;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\OffsitePaymentGatewayBase;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\SupportsNotificationsInterface;
use Drupal\commerce_paytrail\Exception\SecurityHashMismatchException;
use Drupal\commerce_paytrail\RequestBuilder\PaymentRequestBuilder;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\Core\Url;
use Paytrail\Payment\ApiException;
use Paytrail\Payment\Configuration;
use Paytrail\Payment\Model\Payment;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Provides the Paytrail payment gateway.
 *
 * @CommercePaymentGateway(
 *   id = "paytrail",
 *   label = "Paytrail",
 *   display_label = "Paytrail",
 *   forms = {
 *     "offsite-payment" = "Drupal\commerce_paytrail\PluginForm\OffsiteRedirect\PaytrailOffsiteForm",
 *   },
 *   requires_billing_information = FALSE
 * )
 */
class Paytrail extends OffsitePaymentGatewayBase implements SupportsNotificationsInterface {

  use MessengerTrait;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected LanguageManagerInterface $languageManager;

  /**
   * The logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected LoggerInterface $logger;

  /**
   * The payment request builder.
   *
   * @var \Drupal\commerce_paytrail\RequestBuilder\PaymentRequestBuilder
   */
  protected PaymentRequestBuilder $paymentRequestBuilder;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) : static {
    /** @var \Drupal\commerce_paytrail\Plugin\Commerce\PaymentGateway\Paytrail $instance */
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);

    // Populate via setters to avoid overriding the parent constructor.
    $instance->languageManager = $container->get('language_manager');
    $instance->logger = $container->get('logger.channel.commerce_paytrail');
    $instance->paymentRequestBuilder = $container->get('commerce_paytrail.payment_request');

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() : array {
    return [
      'language' => 'automatic',
      'account' => '375917',
      'secret' => 'SAIPPUAKAUPPIAS',
    ] + parent::defaultConfiguration();
  }

  /**
   * Get used langcode.
   */
  public function getLanguage() : string {
    // Attempt to autodetect.
    if ($this->configuration['language'] === 'automatic') {
      $langcode = $this->languageManager->getCurrentLanguage()->getId();

      return in_array($langcode, ['fi', 'sv', 'en']) ? strtoupper($langcode) : 'EN';
    }
    return $this->configuration['language'];
  }

  /**
   * Gets the live mode status.
   *
   * @return bool
   *   Boolean indicating whether we are operating in live mode.
   */
  public function isLive() : bool {
    return $this->configuration['mode'] === 'live';
  }

  /**
   * Gets the client configuration.
   *
   * @return \Paytrail\Payment\Configuration
   *   The client configuration.
   */
  public function getClientConfiguration() : Configuration {
    return (new Configuration())
      ->setApiKey('account', $this->configuration['account'])
      ->setApiKey('secret', $this->configuration['secret'])
      ->setUserAgent('drupal/commerce_paytrail');
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
   * @return \Drupal\Core\Url
   *   The return url.
   */
  protected function buildReturnUrl(OrderInterface $order, string $type, array $arguments = []) : Url {
    $arguments = array_merge([
      'commerce_order' => $order->id(),
      'step' => $arguments['step'] ?? 'payment',
    ], $arguments);

    return (new Url($type, $arguments, ['absolute' => TRUE]));
  }

  /**
   * Gets the return URL for given order.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order.
   *
   * @return \Drupal\Core\Url
   *   The return url.
   */
  public function getReturnUrl(OrderInterface $order) : Url {
    return $this->buildReturnUrl($order, 'commerce_payment.checkout.return');
  }

  /**
   * Gets the cancel URL for given order.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order.
   *
   * @return \Drupal\Core\Url
   *   The cancel url.
   */
  public function getCancelUrl(OrderInterface $order) : Url {
    return $this->buildReturnUrl($order, 'commerce_payment.checkout.cancel');
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) : array {
    $form = parent::buildConfigurationForm($form, $form_state);

    $form['account'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Account'),
      '#required' => TRUE,
      '#default_value' => $this->configuration['account'],
    ];

    $form['secret'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Secret'),
      '#required' => TRUE,
      '#default_value' => $this->configuration['secret'],
    ];

    $form['language'] = [
      '#type' => 'select',
      '#title' => $this->t('Language'),
      '#options' => [
        'automatic' => $this->t('Automatic'),
        'FI' => $this->t('Finnish'),
        'SV' => $this->t('Swedish'),
        'EN' => $this->t('English'),
      ],
      '#default_value' => $this->configuration['language'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);

    if (!$form_state->getErrors()) {
      $values = $form_state->getValue($form['#parents']);

      $this->configuration = $values;
    }
  }

  /**
   * Notify callback.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The response.
   */
  public function onNotify(Request $request) : Response {
    $storage = $this->entityTypeManager->getStorage('commerce_order');

    try {
      /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
      if (!$order = $storage->load($request->get('checkout-reference'))) {
        throw new PaymentGatewayException('Order not found.');
      }
      $this->handlePayment($order, $request);

      return new Response();
    }
    catch (PaymentGatewayException | SecurityHashMismatchException $e) {
    }
    return new Response('', Response::HTTP_FORBIDDEN);
  }

  /**
   * Validate and store transaction for order.
   *
   * Payment will be initially stored as 'authorized' until
   * paytrail calls the notify IPN.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The commerce order.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   */
  public function onReturn(OrderInterface $order, Request $request) : void {
    try {
      $this->handlePayment($order, $request);
    }
    catch (SecurityHashMismatchException | ApiException $e) {
      throw new PaymentGatewayException($e->getMessage());
    }
  }

  /**
   * Creates or captures a payment for given order.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The commerce order.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   *
   * @return \Drupal\commerce_payment\Entity\PaymentInterface
   *   The payment.
   *
   * @throws \Drupal\commerce_paytrail\Exception\SecurityHashMismatchException
   * @throws \Paytrail\Payment\ApiException
   */
  protected function handlePayment(OrderInterface $order, Request $request) : PaymentInterface {
    $this->paymentRequestBuilder
      ->validateSignature($this, $request->query->all())
      // onNotify() uses {commerce_order} to load the order which is not a part
      // of signature hash calculation. Make sure stamp matches with the stamp
      // saved in order entity so a valid return URL cannot be re-used.
      ->validateStamp($order, $request->query->get('checkout-stamp'));

    $paymentResponse = $this->paymentRequestBuilder->get($order);

    $allowedStatuses = [
      Payment::STATUS_OK,
      Payment::STATUS_PENDING,
      Payment::STATUS_DELAYED,
    ];

    if (!in_array($paymentResponse->getStatus(), $allowedStatuses)) {
      throw new PaymentGatewayException('Payment not marked as paid.');
    }
    /** @var \Drupal\commerce_payment\PaymentStorageInterface $storage */
    $storage = $this->entityTypeManager->getStorage('commerce_payment');

    // Create payment if one does not exist yet.
    if (!$payment = $storage->loadByRemoteId($paymentResponse->getTransactionId())) {
      /** @var \Drupal\commerce_payment\Entity\PaymentInterface $payment */
      $payment = $storage->create([
        'payment_gateway' => $this->parentEntity->id(),
        'order_id' => $order->id(),
        'test' => !$this->isLive(),
      ]);
      $payment->setAmount($order->getBalance())
        ->setRemoteId($paymentResponse->getTransactionId())
        ->setRemoteState($paymentResponse->getStatus())
        ->getState()
        ->applyTransitionById('authorize');
    }
    // Make sure to only capture transition once in case Paytrail calls the
    // callback URL and completes the order before customer has returned from
    // the payment gateway.
    if (!$payment->isCompleted() && $paymentResponse->getStatus() === Payment::STATUS_OK) {
      $payment->getState()
        ->applyTransitionById('capture');
      $payment->save();
    }

    return $payment;
  }

}
