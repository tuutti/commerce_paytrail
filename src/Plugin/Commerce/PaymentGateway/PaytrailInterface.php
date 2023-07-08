<?php

declare(strict_types=1);

namespace Drupal\commerce_paytrail\Plugin\Commerce\PaymentGateway;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\PaymentGatewayInterface;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\SupportsRefundsInterface;
use Drupal\commerce_paytrail\Http\PaytrailClient;
use Drupal\Core\Url;

/**
 * Interface for paytrail gateway plugins.
 */
interface PaytrailInterface extends PaymentGatewayInterface, SupportsRefundsInterface {

  public const ACCOUNT = '375917';
  public const SECRET = 'SAIPPUAKAUPPIAS';
  public const STRATEGY_REMOVE_ITEMS = 'remove_items';

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
   * Gets the order discount strategy.
   *
   * Paytrail does not support order level discounts (such as gift cards).
   * This setting allows site owners to choose the strategy how to deal with
   * them.
   *
   * NOTE: This only applies to ORDER level discounts.
   *
   * Available options:
   *
   * 'None': Do nothing. The API request *will* fail if order's total price
   * does
   * not match the total unit price.
   * 'Remove order items': Removes order item information from the API request
   * since it's not mandatory. See
   * https://support.paytrail.com/hc/en-us/articles/6164376177937-New-Paytrail-How-should-discounts-or-gift-cards-be-handled-in-your-online-store-when-using-Paytrail-s-payment-service-.
   *
   * @return string|null
   *   The discount calculation strategy.
   */
  public function orderDiscountStrategy(): ?string;

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
