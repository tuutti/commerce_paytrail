<?php

namespace Drupal\commerce_paytrail\Plugin\Commerce\PaymentGateway;

use Drupal\commerce_payment\Entity\PaymentMethodInterface;
use Drupal\commerce_payment\PaymentMethodTypeManager;
use Drupal\commerce_payment\PaymentTypeManager;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\PaymentGatewayBase;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\SupportsStoredPaymentMethodsInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the Paytrail payment gateway.
 *
 * @CommercePaymentGateway(
 *   id = "paytrail",
 *   label = "Paytrail",
 *   display_label = "Paytrail",
 *   payment_method_types = {"paytrail"},
 *   forms = {
 *     "add-payment-method" = "Drupal\commerce_paytrail\PluginForm\Paytrail\PaymentMethodAddForm",
 *   },
 * )
 */
class Paytrail extends PaymentGatewayBase implements SupportsStoredPaymentMethodsInterface {

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  const HOST = 'https://payment.paytrail.com';
  const DEFAULT_MODE = 1;
  const BYPASS_MODE = 2;

  /**
   * Paytrail constructor.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\commerce_payment\PaymentTypeManager $payment_type_manager
   *   The payment type manager.
   * @param \Drupal\commerce_payment\PaymentMethodTypeManager $payment_method_type_manager
   *   The payment method type manager.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, PaymentTypeManager $payment_type_manager, PaymentMethodTypeManager $payment_method_type_manager, LanguageManagerInterface $language_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_type_manager, $payment_type_manager, $payment_method_type_manager);

    $this->languageManager = $language_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('plugin.manager.commerce_payment_type'),
      $container->get('plugin.manager.commerce_payment_method_type'),
      $container->get('language_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'culture' => 'automatic',
      'merchant_id' => '13466',
      'merchant_hash' => '6pKF4jkv97zmqBJ3ZL8gUw5DfT2NMQ',
      'paytrail_type' => 'S1',
      'paytrail_mode' => static::DEFAULT_MODE,
      'visible_methods' => [],
    ] + parent::defaultConfiguration();
  }

  /**
   * Get setting value.
   *
   * @param string $key
   *   Setting key.
   *
   * @return mixed
   *   Setting value.
   */
  public function getSetting($key) {
    return isset($this->configuration[$key]) ? $this->configuration[$key] : NULL;
  }

  /**
   * Get used langcode.
   */
  public function getCulture() {
    // Attempt to autodetect.
    if ($this->configuration['culture'] === 'automatic') {
      $mapping = [
        'fi' => 'fi_FI',
        'sv' => 'sv_SE',
        'en' => 'en_US',
      ];
      $langcode = $this->languageManager->getCurrentLanguage()->getId();

      return isset($mapping[$langcode]) ? $mapping[$langcode] : 'en_US';
    }
    return $this->configuration['culture'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    $form['merchant_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Merchant ID'),
      '#description' => $this->t('Merchant ID provided by Paytrail.'),
      '#required' => TRUE,
      '#default_value' => $this->configuration['merchant_id'],
    ];

    $form['merchant_hash'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Merchant Authentication Hash'),
      '#description' => $this->t('Authentication Hash code calculated using MD5.'),
      '#required' => TRUE,
      '#default_value' => $this->configuration['merchant_hash'],
    ];

    $form['paytrail_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Type'),
      '#description' => $this->t('S1 is simple version, E1 requires more information.'),
      '#options' => [
        'S1' => $this->t('S1'),
        'E1' => $this->t('E1'),
      ],
      '#default_value' => $this->configuration['paytrail_type'],
    ];

    $form['culture'] = [
      '#type' => 'select',
      '#title' => $this->t('Language'),
      '#description' => $this->t('Affects on default language and how amounts are shown on payment method selection page.'),
      '#options' => [
        'automatic' => $this->t('Automatic'),
        'fi_FI' => $this->t('Finnish'),
        'sv_SE' => $this->t('Swedish'),
        'en_US' => $this->t('English'),
      ],
      '#default_value' => $this->configuration['culture'],
    ];

    $form['paytrail_mode'] = [
      '#type' => 'select',
      '#title' => $this->t('Mode'),
      '#description' => $this->t('Setting this to anything other than normal requires an additional Paytrail services. See @link', [
        '@link' => 'http://support.paytrail.com/hc/en-us/articles/201911337-Payment-page-bypass',
      ]),
      '#options' => [
        static::DEFAULT_MODE => $this->t('Normal service'),
        static::BYPASS_MODE => $this->t('Bypass payment method selection page'),
      ],
      '#default_value' => $this->configuration['paytrail_mode'],
    ];

    $form['visible_methods'] = [
      '#type' => 'select',
      '#title' => $this->t('Visible payment methods'),
      '#description' => $this->t('List of payment methods that are to be shown on the payment page. If left empty all available payment methods shown.'),
      '#options' => [],
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

      $this->configuration = array_merge($this->configuration, [
        'merchant_id' => $values['merchant_id'],
        'merchant_hash'  => $values['merchant_hash'],
        'paytrail_type' => $values['paytrail_type'],
        'paytrail_mode' => $values['paytrail_mode'],
        'visible_methods' => $values['visible_methods'],
        'culture' => $values['culture'],
      ]);
    }
  }

  /**
   * Get payment host url.
   *
   * @return string
   *   Payment url.
   */
  public function getHostUrl() {
    return static::HOST;
  }

  /**
   * {@inheritdoc}
   */
  public function createPaymentMethod(PaymentMethodInterface $payment_method, array $payment_details = NULL) {
    // Payment method should never be reused.
    $payment_method->setReusable(FALSE);
    $payment_method->save();
  }

  /**
   * {@inheritdoc}
   */
  public function deletePaymentMethod(PaymentMethodInterface $payment_method) {
    $payment_method->delete();
  }

}
