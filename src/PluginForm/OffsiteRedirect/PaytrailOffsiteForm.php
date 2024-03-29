<?php

declare(strict_types = 1);

namespace Drupal\commerce_paytrail\PluginForm\OffsiteRedirect;

use Drupal\commerce_payment\PluginForm\PaymentOffsiteForm;
use Drupal\commerce_paytrail\RequestBuilder\PaymentRequestBuilderInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use GuzzleHttp\Exception\RequestException;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the Paytrail payment off-site form.
 */
final class PaytrailOffsiteForm extends PaymentOffsiteForm implements ContainerInjectionInterface {

  use StringTranslationTrait;

  /**
   * Constructs a new instance.
   *
   * @param \Drupal\commerce_paytrail\RequestBuilder\PaymentRequestBuilderInterface $paymentRequest
   *   The payment provider request service.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger interface.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   */
  public function __construct(
    private PaymentRequestBuilderInterface $paymentRequest,
    private LoggerInterface $logger,
    private MessengerInterface $messenger
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) : self {
    return new self(
      $container->get('commerce_paytrail.payment_request'),
      $container->get('logger.channel.commerce_paytrail'),
      $container->get('messenger')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) : array {
    if (!$order = $this->entity->getOrder()) {
      $this->logger
        ->error(sprintf('Payment %s has no order referenced.', $this->entity->id()));
      $this->messenger->addError(
        $this->t('The provided payment has no order referenced. Please contact store administration if the problem persists.')
      );

      return $form;
    }

    /** @var \Paytrail\SDK\Model\Provider $selectedProvider */
    if ($selectedProvider = $form_state->getTemporaryValue('provider')) {
      $data = [];

      foreach ($selectedProvider->getParameters() as $parameter) {
        $data[$parameter->name] = $parameter->value;
      }
      return $this->buildRedirectForm($form, $form_state, $selectedProvider->getUrl(), $data, self::REDIRECT_POST);
    }

    try {
      $response = $this->paymentRequest->create($order);
    }
    catch (RequestException $e) {
      $this->logger
        ->error(sprintf('Paytrail API failure [#%s]: %s', $order->id(), $e->getMessage()));
      $this->messenger->addError(
        $this->t('Failed to fetch payment methods. Please contact store administration if the problem persists.')
      );
      return $form;
    }

    foreach ($response->getGroups() as $group) {
      $form['payment_groups'][$group->getId()] = [
        '#type' => 'fieldset',
        '#title' => $group->getName(),
      ];
    }
    foreach ($response->getProviders() as $provider) {
      $form['payment_groups'][$provider->getGroup()][] = [
        '#type' => 'submit',
        '#value' => $provider->getName(),
        '#provider' => $provider,
        '#submit' => [[$this, 'submitSelectedProvider']],
      ];
    }

    return $form;
  }

  /**
   * Submit callback for payment provider submit button.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $formState
   *   The form state.
   *
   * @return array
   *   The form.
   */
  public function submitSelectedProvider(array $form, FormStateInterface $formState) : array {
    $trigger = $formState->getTriggeringElement();

    if (isset($trigger['#provider'])) {
      $formState->setTemporaryValue('provider', $trigger['#provider']);
    }
    $formState->setRebuild(TRUE);

    return $form;
  }

}
