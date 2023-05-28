<?php

declare(strict_types = 1);

namespace Drupal\commerce_paytrail\PluginForm\OffsiteRedirect;

use Drupal\commerce_payment\PluginForm\PaymentOffsiteForm;
use Drupal\commerce_paytrail\RequestBuilder\TokenPaymentRequestBuilder;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the Paytrail payment off-site form.
 */
final class PaytrailTokenForm extends PaymentOffsiteForm implements ContainerInjectionInterface {

  use StringTranslationTrait;

  /**
   * Constructs a new instance.
   *
   * @param \Drupal\commerce_paytrail\RequestBuilder\TokenPaymentRequestBuilder $tokenRequestBuilder
   *   The token request builder.
   */
  public function __construct(
    private TokenPaymentRequestBuilder $tokenRequestBuilder,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) : self {
    return new self(
      $container->get('commerce_paytrail.token_payment_request')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) : array {
    $form = parent::buildConfigurationForm($form, $form_state);
    $form['payment_details'] = [];

    ['uri' => $uri, 'data' => $data] = $this->tokenRequestBuilder
      ->createAddCardForm($this->entity->getOrder(), $this->plugin);

    return $this->buildRedirectForm($form, $form_state, $uri, $data, self::REDIRECT_POST);
  }

}
