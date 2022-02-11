<?php

declare(strict_types = 1);

namespace Drupal\commerce_paytrail\Plugin\Commerce\PaymentGateway;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\OffsitePaymentGatewayBase;
use Drupal\commerce_paytrail\RequestBuilder\PaymentRequestBuilder;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\Core\Url;
use Paytrail\Payment\Configuration;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for paytrail gateway plugins.
 */
abstract class PaytrailBase extends OffsitePaymentGatewayBase {

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

  public const ACCOUNT = '375917';
  public const SECRET = 'SAIPPUAKAUPPIAS';

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
      'account' => static::ACCOUNT,
      'secret' => static::SECRET,
    ] + parent::defaultConfiguration();
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
   * Get used langcode.
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

}
