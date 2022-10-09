<?php

declare(strict_types = 1);

namespace Drupal\commerce_paytrail;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_paytrail\Exception\PaytrailPluginException;
use Drupal\commerce_paytrail\Plugin\Commerce\PaymentGateway\Paytrail;

/**
 * A trait to interact with the payment gateway.
 */
trait PaymentGatewayPluginTrait {

  /**
   * Gets the plugin for given order.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order.
   *
   * @return \Drupal\commerce_paytrail\Plugin\Commerce\PaymentGateway\Paytrail
   *   The payment plugin.
   */
  protected function getPaymentPlugin(OrderInterface $order) : Paytrail {
    static $plugins = [];

    if (!isset($plugins[$order->id()])) {
      $gateway = $order->get('payment_gateway');

      if ($gateway->isEmpty()) {
        throw new PaytrailPluginException('Payment gateway not found.');
      }
      $plugin = $gateway->first()->entity?->getPlugin();

      if (!$plugin instanceof Paytrail) {
        throw new PaytrailPluginException('Payment gateway not instanceof Paytrail.');
      }
      $plugins[$order->id()] = $plugin;
    }

    return $plugins[$order->id()];
  }

}