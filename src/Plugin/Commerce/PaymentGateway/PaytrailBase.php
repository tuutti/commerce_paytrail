<?php

declare(strict_types = 1);

namespace Drupal\commerce_paytrail\Plugin\Commerce\PaymentGateway;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_payment\Entity\PaymentInterface;
use Drupal\commerce_payment\Exception\PaymentGatewayException;
use Drupal\commerce_payment\PaymentMethodTypeManager;
use Drupal\commerce_payment\PaymentTypeManager;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\OffsitePaymentGatewayBase;
use Drupal\commerce_paytrail\Entity\PaymentMethod;
use Drupal\commerce_paytrail\Exception\InvalidValueException;
use Drupal\commerce_paytrail\Exception\RedirectKeyMismatchException;
use Drupal\commerce_paytrail\Exception\SecurityHashMismatchException;
use Drupal\commerce_paytrail\PaymentManagerInterface;
use Drupal\commerce_paytrail\Repository\Method;
use Drupal\commerce_paytrail\Repository\Response as PaytrailResponse;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Logger\RfcLogLevel;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Provides the Paytrail payment gateway.
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
   * The logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The paytrail host.
   *
   * @var string
   */
  const HOST = 'https://payment.paytrail.com/e2';

  /**
   * The default merchant id used for testing.
   *
   * @var string
   */
  const MERCHANT_ID = '13466';

  /**
   * The default merchant hash used for testing.
   *
   * @var string
   */
  const MERCHANT_HASH = '6pKF4jkv97zmqBJ3ZL8gUw5DfT2NMQ';

  /**
   * The payer details.
   *
   * @var string
   */
  const PAYER_DETAILS = 'payer';

  /**
   * The product details.
   *
   * @var string
   */
  const PRODUCT_DETAILS = 'product';

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
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   * @param \Drupal\commerce_paytrail\PaymentManagerInterface $payment_manager
   *   The payment manager.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, PaymentTypeManager $payment_type_manager, PaymentMethodTypeManager $payment_method_type_manager, TimeInterface $time, LanguageManagerInterface $language_manager, PaymentManagerInterface $payment_manager, LoggerInterface $logger) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_type_manager, $payment_type_manager, $payment_method_type_manager, $time);

    $this->languageManager = $language_manager;
    $this->paymentManager = $payment_manager;
    $this->logger = $logger;
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
      $container->get('datetime.time'),
      $container->get('language_manager'),
      $container->get('commerce_paytrail.payment_manager'),
      $container->get('logger.channel.commerce_paytrail')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'culture' => 'automatic',
      'merchant_id' => static::MERCHANT_ID,
      'merchant_hash' => static::MERCHANT_HASH,
      'bypass_mode' => FALSE,
    ] + parent::defaultConfiguration();
  }

  /**
   * Gets the entity id (plugin id).
   *
   * @return string
   *   The entity id.
   */
  public function getEntityId() {
    return $this->entityId;
  }

  /**
   * Gets the merchant id.
   *
   * @return mixed|string
   *   The merchant id.
   */
  public function getMerchantId() {
    return $this->getMode() == 'test' ? static::MERCHANT_ID : $this->getSetting('merchant_id');
  }

  /**
   * Gets the merchant hash.
   *
   * @return mixed|string
   *   The merchant hash.
   */
  public function getMerchantHash() {
    return $this->getMode() == 'test' ? static::MERCHANT_HASH : $this->getSetting('merchant_hash');
  }

  /**
   * Allow plugin forms to log messages.
   *
   * @todo Should they just use \Drupal?
   *
   * @param string $message
   *   The message to log.
   * @param int $severity
   *   The severity.
   */
  public function log($message, $severity = RfcLogLevel::CRITICAL) {
    $this->logger->log($severity, $message);
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
    return $this->configuration[$key] ?? NULL;
  }

  /**
   * Gets the visible payment methods.
   *
   * @param bool $enabled
   *   Whether to only load enabled payment methods.
   *
   * @return \Drupal\commerce_paytrail\Entity\PaymentMethod[]
   *   The payment methods.
   */
  public function getVisibleMethods($enabled = TRUE) {
    $storage = $this->entityTypeManager->getStorage('paytrail_payment_method');

    if (!$enabled) {
      return $storage->loadMultiple();
    }
    return $storage->loadByProperties(['status' => TRUE]);
  }

  /**
   * Gets the payment manager.
   *
   * @return \Drupal\commerce_paytrail\PaymentManagerInterface
   *   The payment manager.
   */
  public function getPaymentManager() : PaymentManagerInterface {
    return $this->paymentManager;
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
   * Check if given data type should be delivered to Paytrail.
   *
   * @param string $type
   *   The type.
   *
   * @return bool
   *   TRUE if data is included, FALSE if not.
   */
  public function isDataIncluded(string $type) : bool {
    if (isset($this->configuration['included_data'][$type])) {
      return $this->configuration['included_data'][$type] === $type;
    }
    return FALSE;
  }

  /**
   * Checks if the bypass mode is enabled.
   *
   * @return bool
   *   TRUE if enabled, FALSE if not.
   */
  public function isBypassModeEnabled() : bool {
    return $this->configuration['bypass_mode'] ? TRUE : FALSE;
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

    $form['included_data'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Data to deliver'),
      '#default_value' => $this->configuration['included_data'],
      '#options' => [
        static::PRODUCT_DETAILS => $this->t('Product details'),
        static::PAYER_DETAILS => $this->t('Payer details'),
      ],
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
    $form['bypass_mode'] = [
      '#type' => 'checkbox',
      '#title' => $this->t("Bypass Paytrail's payment method selection page"),
      '#description' => $this->t('User will be redirected directly to the selected payment service'),
      '#default_value' => $this->configuration['bypass_mode'],
    ];

    $form['visible_methods'] = [
      '#type' => 'checkboxes',
      '#multiple' => TRUE,
      '#title' => $this->t('Visible payment methods'),
      '#description' => $this->t('List of payment methods that are to be shown on the payment page. If left empty all available payment methods shown.'),
      '#options' => array_map(function (PaymentMethod $value) {
        return $value->adminLabel();
      }, $this->getVisibleMethods(FALSE)),
      '#default_value' => array_map(function (PaymentMethod $value) {
        return $value->id();
      }, $this->getVisibleMethods()),
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

      foreach ($this->getVisibleMethods() as $method) {
        // Enable / disable payment method based on user selection.
        $method->setStatus(in_array($method->id(), $values['visible_methods']))->save();
      }

      $this->configuration = array_merge($this->configuration, [
        'merchant_id' => $values['merchant_id'],
        'merchant_hash'  => $values['merchant_hash'],
        'bypass_mode' => $values['bypass_mode'],
        'included_data' => $values['included_data'],
        'culture' => $values['culture'],
      ]);
    }
  }

  /**
   * IPN callback.
   *
   * IPN will be called after a succesful paytrail payment. Payment will be
   * marked as captured if validation succeeded.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The 200 response code if validation succeeded.
   */
  public function onNotify(Request $request) : Response {
    $order = $this->entityTypeManager
      ->getStorage('commerce_order')
      ->load($request->query->get('ORDER_NUMBER'));

    if (!$order instanceof OrderInterface) {
      $this->logger
        ->notice($this->t('Notify callback called for an invalid order @order [@values]', [
          '@order' => $request->query->get('ORDER_NUMBER'),
          '@values' => print_r($request->query->all(), TRUE),
        ]));

      throw new NotFoundHttpException();
    }

    try {
      $response = PaytrailResponse::createFromRequest($this->getMerchantHash(), $order, $request);
    }
    catch (InvalidValueException $e) {
      throw new HttpException(Response::HTTP_BAD_REQUEST, $e->getMessage());
    }

    try {
      $response->isValidResponse();
    }
    catch (SecurityHashMismatchException $e) {
      $this->logger
        ->notice($this->t('Hash mismatch for @order [@values]', [
          '@order' => $order->id(),
          '@values' => print_r($request->query->all(), TRUE),
        ]));

      return new Response('Hash mismatch.', Response::HTTP_BAD_REQUEST);
    }

    // Mark payment as captured.
    try {
      $this->paymentManager->createPaymentForOrder('capture', $order, $this, $response);
    }
    catch (\InvalidArgumentException $e) {
      // Invalid payment state.
      $this->logger
        ->error($this->t('Invalid payment state for @order [@values]', [
          '@order' => $order->id(),
          '@values' => print_r($request->query->all(), TRUE),
        ]));

      return new Response('Invalid payment state.', Response::HTTP_BAD_REQUEST);
    }
    catch (PaymentGatewayException $e) {
      // Transaction id mismatch.
      $this->logger
        ->error($this->t('Transaction id mismatch for @order [@values]', [
          '@order' => $order->id(),
          '@values' => print_r($request->query->all(), TRUE),
        ]));

      return new Response('Transaction id mismatch.', Response::HTTP_BAD_REQUEST);
    }
    return new Response();
  }

  /**
   * Validate and store transaction for order.
   *
   * Payment will be initially stored as 'authorized' until
   * paytrail calls the notify ipn.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The commerce order.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   */
  public function onReturn(OrderInterface $order, Request $request) : void {
    try {
      $response = PaytrailResponse::createFromRequest($this->getMerchantHash(), $order, $request);
    }
    catch (InvalidValueException $e) {
      drupal_set_message($this->t('Invalid return url'), 'error');

      $this->logger
        ->critical($this->t('Validation failed (@exception) @order [@values]', [
          '@order' => $order->id(),
          '@values' => print_r($request->query->all(), TRUE),
          '@exception' => $e->getMessage(),
        ]));
      throw new PaymentGatewayException();
    }

    try {
      $response->isValidResponse();
    }
    catch (SecurityHashMismatchException $e) {
      drupal_set_message($this->t('Validation failed (security hash mismatch)'), 'error');

      $this->logger
        ->critical($this->t('Hash validation failed @order [@values] (@exception)', [
          '@order' => $order->id(),
          '@values' => print_r($request->query->all(), TRUE),
          '@exception' => $e->getMessage(),
        ]));
      throw new PaymentGatewayException();
    }

    // Mark payment as authorized. Paytrail will attempt to call notify IPN
    // which will mark payment as captured.
    $this->paymentManager->createPaymentForOrder('authorized', $order, $this, $response);

    drupal_set_message($this->t('Payment was processed.'));
  }

}
