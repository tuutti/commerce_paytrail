<?php

namespace Drupal\commerce_paytrail\PluginForm\OffsiteRedirect;

use Drupal\commerce_payment\PluginForm\PaymentOffsiteForm;
use Drupal\commerce_paytrail\Exception\InvalidBillingException;
use Drupal\commerce_paytrail\Exception\InvalidValueException;
use Drupal\Component\Utility\Html;
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
    /** @var \Drupal\commerce_payment\Entity\PaymentInterface $payment */
    $payment = $this->entity;
    /** @var \Drupal\commerce_paytrail\Plugin\Commerce\PaymentGateway\PaytrailBase $plugin */
    $plugin = $payment->getPaymentGateway()->getPlugin();

    try {
      $formInterface = $plugin->getPaymentManager()
        ->buildFormInterface($payment->getOrder(), $plugin);

      // This only works when using the bypass payment page feature.
      if ($plugin->isBypassModeEnabled()) {
        // Attempt to use preselected method if available.
        if ($preselected = $form_state->getTemporaryValue('selected_method')) {
          $formInterface->setPaymentMethods([$preselected]);
        }

        $form['#prefix'] = '<div id="payment-form">';
        $form['#suffix'] = '</div>';

        // Disable auto-redirect so user can select payment method.
        $form['#attached'] = array_filter($form['#attached'], function ($value) {
          return reset($value) !== 'commerce_payment/offsite_redirect';
        });
        // Hide redirect message.
        $form['commerce_message']['#markup'] = NULL;

        $form['payment_methods'] = [
          '#title' => $this->t('Select payment method'),
          '#type' => 'fieldset',
        ];

        foreach ($plugin->getVisibleMethods() as $key => $method) {
          $class = [
            Html::getId($method->label()),
            'payment-button-' . $key,
            'payment-method-button',
          ];
          if ($preselected === $key) {
            $class[] = 'selected';
          }
          $form['payment_methods'][$key] = [
            '#type' => 'submit',
            '#value' => $method->label(),
            '#method_index' => $key,
            '#submit' => [[$this, 'submitSelectedMethod']],
            '#ajax' => [
              'callback' => [$this, 'setSelectedMethod'],
              'wrapper' => 'payment-form',
            ],
            '#attributes' => [
              'class' => $class,
            ],
          ];
        }
      }
      $data = $plugin->getPaymentManager()
        ->dispatch($formInterface, $plugin, $payment->getOrder());

      return $this->buildRedirectForm($form, $form_state, $plugin::HOST, $data, self::REDIRECT_POST);
    }
    catch (InvalidBillingException $e) {
      $plugin->log('Invalid billing data: ' . $e->getMessage());
    }
    catch (InvalidValueException | \InvalidArgumentException $e) {
      $plugin->log('Field validation failed: ' . $e->getMessage());
    }
    catch (\Exception $e) {
      $plugin->log(sprintf('Validation failed (%s: %s)', get_class($e), $e->getMessage()));
    }
    drupal_set_message($this->t('Unexpected error.', 'error'));

    return [];
  }

  /**
   * Submit and store preselected payment method.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function submitSelectedMethod(array $form, FormStateInterface $form_state) {
    $trigger = $form_state->getTriggeringElement();

    if (isset($trigger['#method_index'])) {
      $form_state->setTemporaryValue('selected_method', $trigger['#method_index']);
    }
    $form_state->setRebuild(TRUE);
  }

  /**
   * Set selected method ajax callback.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return mixed
   *   The rendered form.
   */
  public function setSelectedMethod(array $form, FormStateInterface $form_state) {
    // Re-render entire offsite payment form to force MAC to be recalculated
    // every time payment method is changed.
    return $form['payment_process']['offsite_payment'];
  }

}
