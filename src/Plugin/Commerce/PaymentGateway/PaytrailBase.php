<?php

namespace Drupal\commerce_paytrail\Plugin\Commerce\PaymentGateway;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_payment\Exception\PaymentGatewayException;
use Drupal\commerce_payment\PaymentMethodTypeManager;
use Drupal\commerce_payment\PaymentTypeManager;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\OffsitePaymentGatewayBase;
use Drupal\commerce_paytrail\PaymentManagerInterface;
use Drupal\commerce_paytrail\Repository\Method;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Provides the PaytrailBase payment gateway.
 *
 * @CommercePaymentGateway(
 *   id = "paytrail",
 *   label = "Paytrail",
 *   display_label = "Paytrail",
 *   payment_method_types = {"paytrail"},
 *   forms = {
 *     "offsite-payment" = "Drupal\commerce_paytrail\PluginForm\OffsiteRedirect\PaytrailOffsiteForm",
 *   },
 * )
 */
class PaytrailBase extends OffsitePaymentGatewayBase {

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The payment manager.
   *
   * @var \Drupal\commerce_paytrail\PaymentManagerInterface
   */
  protected $paymentManager;

  /**
   * The paytrail host.
   *
   * @var string
   */
  const HOST = 'https://payment.paytrail.com';

  /**
   * The normal payment mode.
   *
   * @var int
   */
  const NORMAL_MODE = 1;

  /**
   * The payment page bypass mode.
   *
   * @var int
   */
  const BYPASS_MODE = 2;

  /**
   * PaytrailBase constructor.
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
   * @param \Drupal\commerce_paytrail\PaymentManagerInterface $payment_manager
   *   The payment manager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, PaymentTypeManager $payment_type_manager, PaymentMethodTypeManager $payment_method_type_manager, LanguageManagerInterface $language_manager, PaymentManagerInterface $payment_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_type_manager, $payment_type_manager, $payment_method_type_manager);

    $this->languageManager = $language_manager;
    $this->paymentManager = $payment_manager;
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
      $container->get('language_manager'),
      $container->get('commerce_paytrail.payment_manager')
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
      'paytrail_mode' => static::NORMAL_MODE,
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
   * Gets the visible payment methods.
   *
   * @return array|mixed
   *   The payment methods.
   */
  public function getVisibleMethods() {
    return $this->paymentManager->getPaymentMethods($this->configuration['visible_methods']);
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
      '#description' => $this->t('Merchant ID provided by PaytrailBase.'),
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
        static::NORMAL_MODE => $this->t('Normal service'),
        static::BYPASS_MODE => $this->t('Bypass payment method selection page'),
      ],
      '#default_value' => $this->configuration['paytrail_mode'],
    ];

    $payment_methods = array_map(function (Method $value) {
      return $value->getLabel();
    }, $this->paymentManager->getPaymentMethods());

    $form['visible_methods'] = [
      '#type' => 'select',
      '#multiple' => TRUE,
      '#title' => $this->t('Visible payment methods'),
      '#description' => $this->t('List of payment methods that are to be shown on the payment page. If left empty all available payment methods shown.'),
      '#options' => $payment_methods,
      '#default_value' => $this->configuration['visible_methods'],
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
   * Get payment manager.
   *
   * This is used in Drupal\commerce_paytrail\PluginForm\PaytrailBase\PaymentMethodAddForm.
   *
   * @todo Check if there is any way to inject this directly into PluginForm.
   *
   * @return \Drupal\commerce_paytrail\PaymentManagerInterface
   *   The payment manager.
   */
  public function getPaymentManager() {
    return $this->paymentManager;
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
  public function onNotify(Request $request) {
    $storage = $this->entityTypeManager->getStorage('commerce_order');

    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    if (!$order = $storage->load($request->query->get('ORDER_NUMBER'))) {
      throw new NotFoundHttpException();
    }
    $redirect_key_match = $this->paymentManager->getRedirectKey($order) === $request->query->get('redirect_key');

    $hash_values = [];
    foreach (['ORDER_NUMBER', 'TIMESTAMP', 'PAID', 'METHOD'] as $key) {
      if (!$value = $request->query->get($key)) {
        continue;
      }
      $hash_values[] = $value;
    }
    $hash = $this->paymentManager->generateReturnChecksum($this->getSetting('merchant_hash'), $hash_values);

    // Check redirect key and checksum validity.
    if (!$redirect_key_match || $hash !== $request->query->get('RETURN_AUTHCODE')) {
      throw new HttpException(Response::HTTP_INTERNAL_SERVER_ERROR);
    }
    // Mark payment as captured.
    $this->paymentManager->createPaymentForOrder('capture', $order, $this, [
      'remote_id' => $request->query->get('PAID'),
      'remote_state' => 'paid',
    ]);
    parent::onNotify($request);
  }

  /**
   * {@inheritdoc}
   */
  public function onReturn(OrderInterface $order, Request $request) {
    $redirect_key_match = $this->paymentManager->getRedirectKey($order) === $request->query->get('redirect_key');

    if (!$redirect_key_match) {
      throw new PaymentGatewayException('Validation failed (redirect key mismatch).');
    }
    // Handle return and notify.
    $hash_values = [];
    foreach (['ORDER_NUMBER', 'TIMESTAMP', 'PAID', 'METHOD'] as $key) {
      if (!$value = $request->query->get($key)) {
        continue;
      }
      $hash_values[] = $value;
    }
    $hash = $this->paymentManager->generateReturnChecksum($this->getSetting('merchant_hash'), $hash_values);

    // Check checksum validity.
    if ($hash !== $request->query->get('RETURN_AUTHCODE')) {
      throw new PaymentGatewayException('Validation failed (security hash mismatch)');
    }
    // Mark payment as authorized. Paytrail will attempt to call notify IPN
    // which will mark payment as captured.
    $this->paymentManager->createPaymentForOrder('authorized', $order, $this, [
      'remote_id' => $request->query->get('PAID'),
      'remote_state' => 'waiting_confirm',
    ]);
    drupal_set_message('Payment was processed');
  }

}
