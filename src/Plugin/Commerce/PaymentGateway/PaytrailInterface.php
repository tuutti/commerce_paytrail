<?php

declare(strict_types=1);

namespace Drupal\commerce_paytrail\Plugin\Commerce\PaymentGateway;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\PaymentGatewayInterface;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\SupportsRefundsInterface;
use Drupal\Core\Url;
use Paytrail\Payment\Configuration;

/**
 * Interface for paytrail gateway plugins.
 */
interface PaytrailInterface extends PaymentGatewayInterface, SupportsRefundsInterface {

  public const ACCOUNT = '375917';
  public const SECRET = 'SAIPPUAKAUPPIAS';
  public const STRATEGY_REMOVE_ITEMS = 'remove_items';

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
   *
   * @return \Drupal\Core\Url
   *   The return url.
   */
  public function getReturnUrl(OrderInterface $order): Url;

  /**
   * Gets the cancel URL for given order.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order.
   *
   * @return \Drupal\Core\Url
   *   The cancel url.
   */
  public function getCancelUrl(OrderInterface $order): Url;

  /**
   * Gets the notify URL for given event.
   *
   * @param string|null $eventName
   *   The event name.
   *
   * @return \Drupal\Core\Url
   *   The return URL.
   */
  public function getNotifyUrl(string $eventName = NULL): Url;

  /**
   * Gets the client configuration.
   *
   * @return \Paytrail\Payment\Configuration
   *   The client configuration.
   */
  public function getClientConfiguration(): Configuration;

}
