<?php

namespace Drupal\commerce_paytrail;

use Drupal\address\AddressInterface;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_payment\Entity\Payment;
use Drupal\commerce_payment\Entity\PaymentInterface;
use Drupal\commerce_paytrail\Plugin\Commerce\PaymentGateway\Paytrail;
use Drupal\commerce_paytrail\Repository\MethodRepository;
use Drupal\commerce_price\Price;
use Drupal\Component\Utility\Crypt;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Site\Settings;
use Drupal\Core\Url;
use Symfony\Component\Routing\Exception\RouteNotFoundException;

/**
 * Class PaymentManager.
 *
 * @package Drupal\commerce_paytrail
 */
class PaymentManager implements PaymentManagerInterface {

  /**
   * Drupal\Core\Entity\EntityTypeManager definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The payment method repository.
   *
   * @var \Drupal\commerce_paytrail\Repository\MethodRepository
   */
  protected $repository;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManager $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler service.
   * @param \Drupal\commerce_paytrail\Repository\MethodRepository $repository
   *   The payment method repository.
   */
  public function __construct(EntityTypeManager $entity_type_manager, ModuleHandlerInterface $module_handler, MethodRepository $repository) {
    $this->entityTypeManager = $entity_type_manager;
    $this->moduleHandler = $module_handler;
    $this->repository = $repository;
  }

  /**
   * Get available payment methods.
   *
   * @param array $enabled
   *   List of enabled payment methods.
   *
   * @return array|mixed
   *   List of available payment methods.
   */
  public function getPaymentMethods(array $enabled = []) {
    $methods = $this->repository->getMethods();

    if (empty($enabled)) {
      return $methods;
    }
    return array_intersect_key($enabled, $methods);
  }

  /**
   * Get return url for given type.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   Order.
   * @param string $type
   *   Return type.
   *
   * @return \Drupal\Core\GeneratedUrl|string
   *   Return absolute return url.
   */
  public function getReturnUrl(OrderInterface $order, $type) {
    $arguments = [
      'commerce_order' => $order->id(),
      'paytrail_redirect_key' => $this->getRedirectKey($order),
      'type' => $type,
    ];

    try {
      $url = new Url('commerce_paytrail.return', $arguments, ['absolute' => TRUE]);
      return $url->toString();
    }
    catch (RouteNotFoundException $e) {
      return NULL;
    }
  }

  /**
   * Get/generate payment redirect key.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   Order.
   *
   * @return string
   *   Payment redirect key.
   */
  public function getRedirectKey(OrderInterface $order) {
    $data = $order->getData();

    // Generate only once.
    if (!empty($data['paytrail_redirect_key'])) {
      return $data['paytrail_redirect_key'];
    }
    $payment_redirect_key = Crypt::hmacBase64(sprintf('%s:%s', $order->id(), REQUEST_TIME), Settings::getHashSalt());

    if (empty($data)) {
      $data = [];
    }
    $data = array_merge($data, [
      'paytrail_redirect_key' => $payment_redirect_key,
    ]);
    $order->setData($data);
    $order->save();

    return $payment_redirect_key;
  }

  /**
   * Store preselected method.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order.
   * @param int $selection
   *   Selection.
   */
  public function setPreselectedMethod(OrderInterface $order, $selection) {
  }

  /**
   * Get preselected order.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order.
   */
  public function getPreselectedMethod(OrderInterface $order) {
  }

  /**
   * Build transaction for order.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   Order.
   *
   * @return array|bool
   *   FALSE on validation failure or transaction array.
   */
  public function buildTransaction(OrderInterface $order) {
    $payment_gateway = $order->payment_gateway->entity;
    $plugin = $payment_gateway->getPlugin();

    if (!$plugin instanceof Paytrail) {
      throw new \InvalidArgumentException('Payment gateway not instance of Paytrail.');
    }
    // @note. Values must be set in correct order to make sure authcode is calculated correctly.
    // @todo Add some kind of validation?
    $values = [
      'MERCHANT_ID' => $plugin->getSetting('merchant_id'),
    ];

    if ($plugin->getSetting('paytrail_type') === 'S1') {
      $values += [
        'AMOUNT' => $this->getOrderTotal($order)->getNumber(),
      ];
    }
    $values += [
      'ORDER_NUMBER' => $order->getOrderNumber(),
      'REFERENCE_NUMBER' => '',
      'ORDER_DESCRIPTION' => '',
      // Only EUR is accepted for Finnish banks and credit cards.
      'CURRENCY' => 'EUR',
      'RETURN_ADDRESS' => $this->getReturnUrl($order, 'return'),
      'CANCEL_ADDRESS' => $this->getReturnUrl($order, 'cancel'),
      'PENDING_ADDRESS' => $this->getReturnUrl($order, 'pending'),
      'NOTIFY_ADDRESS' => $this->getReturnUrl($order, 'notify'),
      'TYPE' => $plugin->getSetting('paytrail_type'),
      'CULTURE' => $plugin->getCulture(),
      'PRESELECTED_METHOD' => '',
      'MODE' => $plugin->getSetting('paytrail_mode'),
      'VISIBLE_METHODS' => implode(',', $plugin->getSetting('visible_methods')),
      // This has not yet been implemented by Paytrail.
      'GROUP' => '',
    ];

    if ($plugin->getSetting('paytrail_type') === 'E1') {
      $billing_data = $order->getBillingProfile()->get('address')->first();

      // Billing data not found.
      if (!$billing_data instanceof AddressInterface) {
        return FALSE;
      }
      $names = explode(' ', $billing_data->getGivenName());

      // Lastname is required field by Paytrail, but not by billing profile.
      // Fallback to double first names.
      if (empty($names[1])) {
        $names[1] = reset($names);
      }
      list($firstname, $lastname) = $names;

      $values += [
        'CONTACT_TELLNO' => '',
        'CONTACT_CELLNO' => '',
        'CONTACT_EMAIL' => $order->getEmail(),
        'CONTACT_FIRSTNAME' => substr($firstname, 0, 64),
        'CONTACT_LASTNAME' => substr($lastname, 0, 64),
        'CONTACT_COMPANY' => substr($billing_data->getOrganization(), 0, 64),
        'CONTACT_ADDR_STREET' => substr($billing_data->getAddressLine1(), 0, 128),
        'CONTACT_ADDR_ZIP' => substr($billing_data->getPostalCode(), 0, 16),
        'CONTACT_ADDR_CITY' => substr($billing_data->getLocality(), 0, 64),
        'CONTACT_ADDR_COUNTRY' => $billing_data->getCountryCode(),
        // @todo Check commerce settings.
        'INCLUDE_VAT' => '1',
        'ITEMS' => count($order->getItems()),
      ];

      foreach ($order->getItems() as $delta => $item) {
        $temp_value = [
          'ITEM_TITLE' => $item->getTitle(),
          'ITEM_NO' => '',
          'ITEM_AMOUNT' => round($item->getQuantity()),
          'ITEM_PRICE' => number_format($item->getTotalPrice()->getNumber(), 2, '.', ''),
          // @todo Implement this once commerce_tax is implemented again.
          'ITEM_TAX' => 0,
          // @todo Implement this.
          'ITEM_DISCOUNT' => 0,
          // Default to product (1=product, 2=shipping fees, 3=handling fees).
          // @todo Implement this.
          'ITEM_TYPE' => 1,
        ];
        foreach ($temp_value as $key => $value) {
          $values[$key . '[' . $delta . ']'] = $value;
        }
      }
    }
    $order_clone = clone $order;
    // Allow elements to be altered.
    $this->moduleHandler->alter('commerce_paytrail_payment', $values, $order_clone, $plugin);

    $values['AUTHCODE'] = $this->generateAuthCode($plugin->getSetting('merchant_hash'), $values);

    return $values;
  }

  /**
   * Attempt to fetch payment for given order.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order.
   *
   * @return bool|\Drupal\commerce_paytrail\PaymentInterface
   *   Payment object on success, FALSE on failure.
   */
  public function getPayment(OrderInterface $order) {
    /** @var PaymentInterface[] $payments */
    $payments = $this->entityTypeManager
      ->getStorage('commerce_payment')
      ->loadByProperties(['order_id' => $order->id()]);

    if (empty($payments)) {
      return FALSE;
    }
    foreach ($payments as $payment) {
      if ($payment->bundle() !== 'paytrail' || $payment->getAmount()->compareTo($order->getTotalPrice()) !== 0) {
        continue;
      }
      $paytrail_payment = $payment;
    }
    return empty($paytrail_payment) ? FALSE : $paytrail_payment;
  }

  /**
   * Create payment entity for given order.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The Order.
   *
   * @return \Drupal\Core\Entity\EntityInterface|static
   *   The payment entity.
   */
  public function buildPayment(OrderInterface $order) {
    // Attempt to get existing payment.
    if ($payment = $this->getPayment($order)) {
      return $payment;
    }
    $payment = Payment::create([
      'type' => 'paytrail',
      'payment_method' => $order->payment_method->target_id,
      'payment_gateway' => $order->payment_gateway->target_id,
      'order_id' => $order->id(),
      'amount' => $order->getTotalPrice(),
      'paytrail_redirect_url' => $this->getRedirectKey($order),
    ]);
    $payment->save();

    return $payment;
  }

  /**
   * Complete payment.
   *
   * @param \Drupal\commerce_payment\Entity\PaymentInterface $payment
   *   The payment.
   * @param string $status
   *   Payment status.
   *
   * @return bool
   *   Status of payment.
   */
  public function completePayment(PaymentInterface $payment, $status) {
    // Payment failed. Delete payment.
    if ($status === PaymentStatus::FAILED) {
      $payment->delete();

      return FALSE;
    }
    elseif ($status === PaymentStatus::SUCCESS) {
      $transition = $payment->getState()->getWorkflow()->getTransition('authorize');
      $payment->getState()->applyTransition($transition);
      $payment->save();

      return TRUE;
    }
  }

  /**
   * Complete commerce order.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order.
   */
  public function completeOrder(OrderInterface $order) {
    // Place the order.
    $transition = $order->getState()->getWorkflow()->getTransition('place');
    $order->getState()->applyTransition($transition);
    $order->set('checkout_step', 'complete');
    $order->save();
  }

  /**
   * Calculate authcode for transaction.
   *
   * @param string $hash
   *   Merchant hash.
   * @param array $values
   *   Values used to generate mac.
   *
   * @return string
   *   Authcode hash.
   */
  public function generateAuthCode($hash, array $values) {
    return strtoupper(md5($hash . '|' . implode('|', $values)));
  }

  /**
   * Calculate return checksum.
   *
   * @param string $hash
   *   Merchant hash.
   * @param array $values
   *   Values used to generate mac.
   *
   * @return string
   *   Checksum.
   */
  public function generateReturnChecksum($hash, array $values) {
    return strtoupper(md5(implode('|', $values) . '|' . $hash));
  }

  /**
   * Get rounded total price.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order.
   *
   * @return \Drupal\commerce_price\Price
   *   Total price object.
   */
  public function getOrderTotal(OrderInterface $order) {
    $order_total = $order->getTotalPrice();
    $currency_code = $order_total->getCurrencyCode();

    $rounded = number_format($order_total->getNumber(), 2, '.', '');

    return new Price($rounded, $currency_code);
  }

}
