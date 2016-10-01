<?php

namespace Drupal\commerce_paytrail\Plugin\Commerce\CheckoutPane;

use Drupal\commerce_checkout\Plugin\Commerce\CheckoutFlow\CheckoutFlowInterface;
use Drupal\commerce_checkout\Plugin\Commerce\CheckoutPane\CheckoutPaneBase;
use Drupal\commerce_checkout\Plugin\Commerce\CheckoutPane\CheckoutPaneInterface;
use Drupal\commerce_paytrail\PaymentManagerInterface;
use Drupal\commerce_paytrail\Plugin\Commerce\PaymentGateway\Paytrail as PaytrailGateway;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the payment type selection pane.
 *
 * @CommerceCheckoutPane(
 *   id = "commerce_paytrail",
 *   label = @Translation("Paytrail"),
 *   default_step = "offsite_payment",
 *   wrapper_element = "fieldset",
 * )
 */
class Paytrail extends CheckoutPaneBase implements CheckoutPaneInterface, ContainerFactoryPluginInterface {

  /**
   * The payment manager service.
   *
   * @var \Drupal\commerce_paytrail\PaymentManagerInterface
   */
  protected $paymentManager;

  /**
   * Paytrail constructor.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\commerce_checkout\Plugin\Commerce\CheckoutFlow\CheckoutFlowInterface $checkout_flow
   *   The parent checkout flow.
   * @param \Drupal\commerce_paytrail\PaymentManagerInterface $payment_manager
   *   The payment manager service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, CheckoutFlowInterface $checkout_flow, PaymentManagerInterface $payment_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $checkout_flow);

    $this->paymentManager = $payment_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition, CheckoutFlowInterface $checkout_flow = NULL) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $checkout_flow,
      $container->get('commerce_paytrail.payment_manager')
    );
  }

  /**
   * Builds the pane form.
   *
   * @param array $pane_form
   *   The pane form, containing the following basic properties:
   *   - #parents: Identifies the position of the pane form in the overall
   *     parent form, and identifies the location where the field values are
   *     placed within $form_state->getValues().
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state of the parent form.
   * @param array $complete_form
   *   The complete form structure.
   *
   * @return array
   *   Pane form.
   */
  public function buildPaneForm(array $pane_form, FormStateInterface $form_state, array &$complete_form) {
    $payment_gateway = $this->order->payment_gateway->entity;
    $plugin = $payment_gateway->getPlugin();

    if (!$plugin instanceof PaytrailGateway) {
      throw new \InvalidArgumentException('Payment gateway not instance of Paytrail.');
    }
    $elements = $this->paymentManager->buildTransaction($this->order);

    // @todo Better way to do this. At the moment payment must be on separate checkout.
    $complete_form['#action'] = $plugin->getHostUrl();

    foreach ($elements as $key => $value) {
      $complete_form[$key] = [
        '#type' => 'hidden',
        '#value' => $value,
      ];
    }
    $pane_form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Continue to payment service'),
    ];
    return $pane_form;
  }

}
