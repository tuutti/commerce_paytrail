<?php

namespace Drupal\commerce_paytrail\PluginForm\OffsiteRedirect;

use Drupal\commerce_payment\PluginForm\PaymentOffsiteForm;
use Drupal\commerce_paytrail\Exception\InvalidValueException;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Class PaytrailOffsiteForm.
 *
 * @package Drupal\commerce_paytrail\PluginForm\OffsiteRedirect
 */
class PaytrailOffsiteForm extends PaymentOffsiteForm {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    /** @var \Drupal\commerce_payment\Entity\PaymentInterface $payment */
    $payment = $this->entity;
    /** @var \Drupal\commerce_paytrail\Plugin\Commerce\PaymentGateway\PaytrailBase $payment_gateway_plugin */
    $payment_gateway_plugin = $payment->getPaymentGateway()->getPlugin();
    $payment_manager = $payment_gateway_plugin->getPaymentManager();

    try {
      $data = $payment_manager->buildTransaction($payment->getOrder(), $payment_gateway_plugin);

      return $this->buildRedirectForm($form, $form_state, $payment_gateway_plugin->getHostUrl(), $data, 'post');
    }
    catch (InvalidValueException $e) {
      drupal_set_message($e->getMessage(), 'error');
    }
    catch (\Exception $e) {
      drupal_set_message($this->t('Unexpected error. Please contact store administration if the problem persists.'), 'error');
    }
    return [];
  }

}
