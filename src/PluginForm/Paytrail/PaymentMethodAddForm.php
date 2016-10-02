<?php

namespace Drupal\commerce_paytrail\PluginForm\Paytrail;

use Drupal\commerce_payment\PluginForm\PaymentMethodAddForm as BasePaymentMethodAddForm;
use Drupal\commerce_paytrail\Plugin\Commerce\PaymentGateway\Paytrail;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class PaymentMethodAddForm.
 *
 * @package Drupal\commerce_paytrail\PluginForm\Paytrail
 */
class PaymentMethodAddForm extends BasePaymentMethodAddForm {

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\commerce_payment\Entity\PaymentMethodInterface $payment_method */
    $payment_method = $this->entity;

    $form = parent::buildConfigurationForm($form, $form_state);
    // Add payment methods if bypass payment page mode is enabled.
    if ($payment_method->bundle() === 'paytrail') {
      if ($this->plugin->getSetting('paytrail_mode') == Paytrail::BYPASS_MODE) {
        $form['payment_details'] = $this->buildPaytrailForm($form['payment_details'], $form_state);
      }
    }
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * Build preselected method form for Paytrail.
   *
   * @param array $element
   *   Form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   *
   * @return array
   *   Form array.
   */
  protected function buildPaytrailForm(array $element, FormStateInterface $form_state) {
    // Fetch list of enabled payment methods.
    $payment_methods = $this->plugin->getPaymentManager()
      ->getPaymentMethods($this->plugin->getSetting('visible_methods'));

    $options = array_map(function ($value) {
      return $value->getDisplayLabel();
    }, $payment_methods);

    $element['preselected_method'] = [
      '#type' => 'radios',
      '#options' => $options,
      '#required' => TRUE,
      '#default_value' => $form_state->getValue('preselected_method'),
    ];
    return $element;
  }

}
