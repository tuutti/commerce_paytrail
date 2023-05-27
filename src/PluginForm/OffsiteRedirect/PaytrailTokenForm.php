<?php

declare(strict_types = 1);

namespace Drupal\commerce_paytrail\PluginForm\OffsiteRedirect;

use Drupal\commerce_payment\PluginForm\PaymentOffsiteForm;
use Drupal\commerce_paytrail\RequestBuilder\TokenPaymentRequestBuilder;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the Paytrail payment off-site form.
 */
final class PaytrailTokenForm extends PaymentOffsiteForm implements ContainerInjectionInterface {

  use StringTranslationTrait;

  private MessengerInterface $messenger;
  private TokenPaymentRequestBuilder $builder;
  private RouteMatchInterface $routeMatch;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) : self {
    $instance = new self();
    $instance->builder = $container->get('commerce_paytrail.token_payment_request');
    $instance->messenger = $container->get('messenger');
    $instance->routeMatch = $container->get('current_route_match');

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) : array {
    $form = parent::buildConfigurationForm($form, $form_state);
    $form['payment_details'] = [];

    $values = $this->builder->createAddCardForm($this->plugin, $this->entity->getOrder());

    return $this->buildRedirectForm($form, $form_state, 'https://services.paytrail.com/tokenization/addcard-form', $values, self::REDIRECT_POST);
  }

  /**
   * Prepares the complete form for a POST redirect.
   *
   * Sets the form #action, adds a class for the JS to target.
   * Workaround for buildConfigurationForm() not receiving $complete_form.
   *
   * @param array $form
   *   The plugin form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param array $complete_form
   *   The complete form structure.
   *
   * @return array
   *   The processed form element.
   */
  public static function processRedirectForm(array $form, FormStateInterface $form_state, array &$complete_form) {
    $complete_form['#action'] = $form['#redirect_url'];
    $complete_form['#attributes']['class'][] = 'payment-redirect-form';
    // The form actions are hidden by default, but needed in this case.
    $complete_form['actions']['#access'] = TRUE;
    foreach (Element::children($complete_form['actions']) as $element_name) {
      $complete_form['actions'][$element_name]['#access'] = TRUE;
    }

    return $form;
  }

}
