<?php

declare(strict_types=1);

namespace Drupal\commerce_paytrail\Plugin\Commerce\PaymentGateway;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\OffsitePaymentGatewayInterface;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\SupportsRefundsInterface;
use Drupal\commerce_paytrail\Http\PaytrailClient;
use Drupal\Core\Url;

/**
 * Interface for paytrail gateway plugins.
 */
interface PaytrailInterface extends OffsitePaymentGatewayInterface, SupportsRefundsInterface {

  public const ACCOUNT = '375917';
  public const SECRET = 'SAIPPUAKAUPPIAS';

  /**
   * Gets the merchant account.
   *
   * @return int
   *   The merchant account.
   */
  public function getAccount() : int;

  /**
   * Gets the merchant secret.
   *
   * @return string
   *   The merchant secret.
   */
  public function getSecret() : string;

  /**
   * Get used langcode.
   */
  public function getLanguage(): string;

  /**
   * Gets the live mode status.
   *
   * @return bool
   *   Boolean indicating whether we are operating in live mode.
   */
  public function isLive(): bool;

  /**
   * Gets the return URL for given order.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order.
   * @param array $query
   *   An additional query arguments.
   *
   * @return \Drupal\Core\Url
   *   The return url.
   */
  public function getReturnUrl(OrderInterface $order, array $query = []): Url;

  /**
   * Gets the cancel URL for given order.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order.
   * @param array $query
   *   An additional query arguments.
   *
   * @return \Drupal\Core\Url
   *   The cancel url.
   */
  public function getCancelUrl(OrderInterface $order, array $query = []): Url;

  /**
   * Gets the notify URL for given event.
   *
   * @param array $query
   *   An additional query arguments.
   *
   * @return \Drupal\Core\Url
   *   The return URL.
   */
  public function getNotifyUrl(array $query = []): Url;

  /**
   * Gets the Paytrail HTTP client.
   *
   * @return \Drupal\commerce_paytrail\Http\PaytrailClient
   *   The client.
   */
  public function getClient(): PaytrailClient;

}
