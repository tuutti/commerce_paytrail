<?php

declare(strict_types = 1);

namespace Drupal\Tests\commerce_paytrail\Functional;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_store\StoreCreationTrait;
use Drupal\Tests\commerce_paytrail\Traits\ApiTestTrait;
use Paytrail\Payment\Model\Payment;

/**
 * Tests return pages.
 *
 * @group commerce_paytrail
 */
class ReturnPageTest extends PaytrailBrowserTestBase {

  use StoreCreationTrait;
  use ApiTestTrait;

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'commerce_product',
    'commerce_cart',
    'commerce_checkout',
    'commerce_payment',
    'commerce_order',
    'commerce_order_test',
    'inline_entity_form',
    'commerce_paytrail',
  ];

  /**
   * Gets the order.
   *
   * @return \Drupal\commerce_order\Entity\OrderInterface
   *   The order.
   */
  private function getOrder(): OrderInterface {
    $order_item = $this->createEntity('commerce_order_item', [
      'type' => 'default',
      'unit_price' => [
        'number' => '999',
        'currency_code' => 'EUR',
      ],
    ]);
    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $order = $this->createEntity('commerce_order', [
      'order_id' => 124,
      'type' => 'default',
      'mail' => $this->loggedInUser->getEmail(),
      'order_items' => [$order_item],
      'uid' => $this->loggedInUser,
      'store_id' => $this->store,
      'state' => 'draft',
      'checkout_flow' => 'default',
      'checkout_step' => 'payment',
      'payment_gateway' => $this->gateway,
    ]);
    return $order;
  }

  /**
   * {@inheritdoc}
   */
  protected function getAdministratorPermissions() : array {
    return array_merge([
      'administer commerce_order',
      'administer commerce_order_type',
      'access commerce_order overview',
    ], parent::getAdministratorPermissions());
  }

  /**
   * Tests return callbacks.
   */
  public function testReturn() : void {
    $payment = (new Payment())
      ->setStatus(Payment::STATUS_OK)
    $this->container->set('http_client', $client);

    $args = [
      'checkout-account' => '375917',
      'checkout-algorithm' => 'sha512',
      'checkout-amount' => '12300',
      'checkout-stamp' => '3cbc14b8-5c50-4cfb-a5c0-7ed398ef77c2',
      'checkout-reference' => '124',
      'checkout-transaction-id' => 'ab4713c2-3a37-11ec-a94f-cbbf734f44ee',
      'checkout-status' => 'ok',
      'checkout-provider' => 'osuuspankki',
      'signature' => '31b47b12a48ee4693f87ef0666db707d18d612f74a6f68d79a56a1ee15460a636c6f4a01fb0a6d64b7e290d613d13e31e57d555fe8db02ba2b3fd4f04abb26f5',
    ];
    $order = $this->getOrder();
    $this->drupalGet($this->gatewayPlugin->getReturnUrl($order)->toString(), ['query' => $args]);
  }

}
