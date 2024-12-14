<?php

declare(strict_types=1);

namespace Drupal\commerce_paytrail\PluginForm\OffsiteRedirect;

use Drupal\commerce_payment\PluginForm\PaymentOffsiteForm;
use Drupal\commerce_paytrail\RequestBuilder\TokenRequestBuilderInterface;
use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Provides the Paytrail token off-site form.
 */
final class PaytrailTokenForm extends PaymentOffsiteForm implements ContainerInjectionInterface {

  use StringTranslationTrait;
  use AutowireTrait;

  /**
   * Constructs a new instance.
   *
   * @param \Drupal\commerce_paytrail\RequestBuilder\TokenRequestBuilderInterface $tokenRequestBuilder
   *   The token request builder.
   */
  public function __construct(
    private TokenRequestBuilderInterface $tokenRequestBuilder,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) : array {
    $form = parent::buildConfigurationForm($form, $form_state);
    $form['payment_details'] = [];

    ['uri' => $uri, 'data' => $data] = $this->tokenRequestBuilder
      ->createAddCardFormForOrder($this->entity->getOrder(), $form['#capture']);

    return $this->buildRedirectForm($form, $form_state, $uri, $data, self::REDIRECT_POST);
  }

}
