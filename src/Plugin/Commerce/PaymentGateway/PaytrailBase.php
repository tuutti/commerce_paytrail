<?php

declare(strict_types = 1);

namespace Drupal\commerce_paytrail\Plugin\Commerce\PaymentGateway;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_payment\Entity\PaymentInterface;
use Drupal\commerce_payment\Exception\PaymentGatewayException;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\OffsitePaymentGatewayBase;
use Drupal\commerce_paytrail\ExceptionHelper;
use Drupal\commerce_paytrail\Http\PaytrailClient;
use Drupal\commerce_paytrail\Http\PaytrailClientFactory;
use Drupal\commerce_paytrail\RequestBuilder\RefundRequestBuilderInterface;
use Drupal\commerce_paytrail\SignatureTrait;
use Drupal\commerce_price\Price;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\Core\Url;
use Paytrail\Payment\Model\RefundResponse;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for paytrail gateway plugins.
 */
abstract class PaytrailBase extends OffsitePaymentGatewayBase implements PaytrailInterface {

  use MessengerTrait;
  use SignatureTrait;

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
   * The refund request builder.
   *
   * @var \Drupal\commerce_paytrail\RequestBuilder\RefundRequestBuilderInterface
   */
  protected RefundRequestBuilderInterface $refundRequest;

  /**
   * The paytrail client factory.
   *
   * @var \Drupal\commerce_paytrail\Http\PaytrailClientFactory
   */
  protected PaytrailClientFactory $clientFactory;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) : static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->languageManager = $container->get('language_manager');
    $instance->logger = $container->get('logger.channel.commerce_paytrail');
    $instance->refundRequest = $container->get('commerce_paytrail.refund_request');
    $instance->clientFactory = $container->get('commerce_paytrail.paytrail_client_factory');

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() : array {
    return [
      'language' => 'automatic',
      'account' => static::ACCOUNT,
      'secret' => static::SECRET,
      'payment_method_types' => ['paytrail'],
      'order_discount_strategy' => NULL,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function getAccount() : int {
    return (int) $this->configuration['account'];
  }

  /**
   * {@inheritdoc}
   */
  public function getSecret() : string {
    return $this->configuration['secret'];
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration) : void {
    $this->configuration = array_merge($this->defaultConfiguration(), $configuration);
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

    $form['order_discount_strategy'] = [
      '#type' => 'radios',
      '#title' => $this->t('Order discount strategy'),
      // @todo Support splitting discount amount into order items.
      '#options' => [
        NULL => $this->t('<b>Do nothing</b>: The API request will fail if you have any order level discounts'),
        static::STRATEGY_REMOVE_ITEMS => $this->t('<b>Remove order item information</b>: The order item data will not be included in the API request. See the link below for implications.'),
      ],
      '#default_value' => $this->configuration['order_discount_strategy'],
      '#description' => $this->t('<p>Paytrail does not support order level discounts, such as gift cards. See <a href="@link">this link</a> for more information.</p><p>This setting <em>does not</em> affect most discounts applied by <code>commerce_promotion</code> module, since they are split across all order items.</p>',
        [
          '@link' => 'https://support.paytrail.com/hc/en-us/articles/6164376177937-New-Paytrail-How-should-discounts-or-gift-cards-be-handled-in-your-online-store-when-using-Paytrail-s-payment-service-',
        ]),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) : void {
    parent::submitConfigurationForm($form, $form_state);

    if (!$form_state->getErrors()) {
      $values = $form_state->getValue($form['#parents']);

      $this->configuration = $values;
    }
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
   * {@inheritdoc}
   */
  public function getLanguage() : string {
    // Attempt to autodetect.
    if ($this->configuration['language'] === 'automatic') {
      return match($this->languageManager->getCurrentLanguage()->getId()) {
        'fi' => 'FI',
        'sv' => 'SV',
        default => 'EN',
      };
    }
    return $this->configuration['language'];
  }

  /**
   * {@inheritdoc}
   */
  public function isLive() : bool {
    return $this->configuration['mode'] === 'live';
  }

  /**
   * {@inheritdoc}
   */
  public function orderDiscountStrategy() : ? string {
    return $this->configuration['order_discount_strategy'];
  }

  /**
   * {@inheritdoc}
   */
  public function getReturnUrl(OrderInterface $order) : Url {
    return $this->buildReturnUrl($order, 'commerce_payment.checkout.return');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl(OrderInterface $order) : Url {
    return $this->buildReturnUrl($order, 'commerce_payment.checkout.cancel');
  }

  /**
   * {@inheritdoc}
   */
  public function getNotifyUrl(string $eventName = NULL) : Url {
    $url = parent::getNotifyUrl();

    if ($eventName) {
      $query = $url->getOption('query');
      $query['event'] = $eventName;
      $url->setOption('query', $query);
    }
    return $url;
  }

  /**
   * {@inheritdoc}
   */
  public function getClient() : PaytrailClient {
    static $client;

    if (!$client) {
      $client = $this->clientFactory
        ->create($this->getAccount(), $this->getSecret(), 'drupal/commerce_paytrail');
    }
    return $client;
  }

  /**
   * Validates the response status.
   *
   * @param string $response
   *   The actual status.
   * @param array $allowedStatuses
   *   The allowed statuses.
   */
  protected function assertResponseStatus(string $response, array $allowedStatuses) : void {
    if (!in_array($response, $allowedStatuses)) {
      throw new PaymentGatewayException(
        sprintf('Invalid status: %s [allowed: %s]', $response, implode(',', $allowedStatuses))
      );
    }
  }

  /**
   * {@inheritdoc}
   */
  public function refundPayment(PaymentInterface $payment, Price $amount = NULL) : void {
    $this->assertPaymentState($payment, ['completed', 'partially_refunded']);
    // If not specified, refund the entire amount.
    $amount = $amount ?: $payment->getAmount();
    // Validate the requested amount.
    $this->assertRefundAmount($payment, $amount);
    $order = $payment->getOrder();

    $oldRefundedAmount = $payment->getRefundedAmount();
    $newRefundedAmount = $oldRefundedAmount->add($amount);

    try {
      $response = $this->refundRequest->refund($payment->getRemoteId(), $order, $amount);

      $this->assertResponseStatus($response->getStatus(), [
        RefundResponse::STATUS_OK,
        RefundResponse::STATUS_PENDING,
      ]);

      $newRefundedAmount->lessThan($payment->getAmount()) ?
        $payment->setState('partially_refunded') :
        $payment->setState('refunded');

      $payment->setRefundedAmount($newRefundedAmount)
        ->save();
    }
    catch (\Exception $e) {
      ExceptionHelper::handle($e);
    }
  }

}
