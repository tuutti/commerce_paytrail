<?php

declare(strict_types = 1);

namespace Drupal\commerce_paytrail\Repository;

use Drupal\address\AddressInterface;
use Drupal\commerce_paytrail\Entity\PaymentMethod;
use Drupal\commerce_price\Price;
use Webmozart\Assert\Assert;

/**
 * Provides form interface base class.
 */
class FormManager extends BaseResource {

  /**
   * Array of form values.
   *
   * @var \Drupal\commerce_paytrail\Repository\FormValue[]
   */
  protected $values = [];

  /**
   * Array of paytrail products.
   *
   * @var \Drupal\commerce_paytrail\Repository\Product[]
   */
  protected $products = [];

  /**
   * Constructs a new instance.
   *
   * @param string $merchant_id
   *   The merchant id.
   * @param string $merchant_hash
   *   The merchant hash.
   */
  public function __construct(string $merchant_id, string $merchant_hash) {
    parent::__construct($merchant_hash);

    $this->setMerchantId($merchant_id)
      ->setParamsIn(['PARAMS_IN']);
  }

  /**
   * Sets the value.
   *
   * @param string $key
   *   The key.
   * @param int|string|float $value
   *   The value.
   *
   * @return \Drupal\commerce_paytrail\Repository\FormManager
   *   The self.
   */
  protected function setValue(string $key, $value) : self {
    $this->values[$key] = new FormValue($key, $value);
    return $this;
  }

  /**
   * Removes the given value.
   *
   * @param string $key
   *   The key.
   *
   * @return \Drupal\commerce_paytrail\Repository\FormManager
   *   The self.
   */
  protected function removeValue(string $key) : self {
    unset($this->values[$key]);

    return $this;
  }

  /**
   * Sets the parameters sent to Paytrail.
   *
   * @param array $params
   *   The params.
   *
   * @return $this
   *   The self.
   */
  public function setParamsIn(array $params) : self {
    return $this->setValue('PARAMS_IN', implode(',', $params));
  }

  /**
   * Sets the parameters sent when returning from the payment gateway.
   *
   * @param array $params
   *   The params.
   *
   * @return $this
   *   The self.
   */
  public function setParamsOut(array $params) : self {
    return $this->setValue('PARAMS_OUT', implode(',', $params));
  }

  /**
   * Sets the total price.
   *
   * @param \Drupal\commerce_price\Price $amount
   *   The total price.
   *
   * @return $this
   *   The self.
   */
  public function setAmount(Price $amount) : self {
    return $this->setValue('AMOUNT', number_format((float) $amount->getNumber(), 2, '.', ''));
  }

  /**
   * Sets the merchant id.
   *
   * @param string $id
   *   The merchant id.
   *
   * @return $this
   *   The self.
   */
  public function setMerchantId(string $id) : self {
    return $this->setValue('MERCHANT_ID', $id);
  }

  /**
   * Sets the success url.
   *
   * @param string $url
   *   The success url.
   *
   * @return $this
   *   The self.
   */
  public function setSuccessUrl(string $url) : self {
    return $this->setValue('URL_SUCCESS', $url);
  }

  /**
   * Sets the cancel url.
   *
   * @param string $url
   *   The cancel url.
   *
   * @return $this
   *   The self.
   */
  public function setCancelUrl(string $url) : self {
    return $this->setValue('URL_CANCEL', $url);
  }

  /**
   * Sets the notify url.
   *
   * @param string $url
   *   The notify url.
   *
   * @return $this
   *   The self.
   */
  public function setNotifyUrl(string $url) : self {
    return $this->setValue('URL_NOTIFY', $url);
  }

  /**
   * Sets the order number.
   *
   * @param string $orderNumber
   *   The order number.
   *
   * @return $this
   *   The self.
   */
  public function setOrderNumber(string $orderNumber) : self {
    return $this->setValue('ORDER_NUMBER', $orderNumber);
  }

  /**
   * Sets the products.
   *
   * @param \Drupal\commerce_paytrail\Repository\Product[] $products
   *   The products.
   *
   * @return $this
   *   The self.
   */
  public function setProducts(array $products) : self {
    Assert::allIsInstanceOf($products, Product::class);
    $this->products = $products;

    return $this;
  }

  /**
   * Sets the product.
   *
   * @param \Drupal\commerce_paytrail\Repository\Product $product
   *   The product.
   *
   * @return $this
   *   The self.
   */
  public function setProduct(Product $product) : self {
    $this->products[] = $product;
    return $this;
  }

  /**
   * Sets the message shown in Merchant's Panel.
   *
   * @param string $message
   *   The message.
   *
   * @return $this
   *   The self.
   */
  public function setMerchantPanelUiMessage(string $message) : self {
    return $this->setValue('MSG_UI_MERCHANT_PANEL', $message);
  }

  /**
   * Sets the payment method ui message.
   *
   * Message shown in payment method provider page. Currently this is
   * supported by Osuuspankki, Visa (Nets), MasterCard (Nets),
   * American Express (Nets) and Diners Club (Nets).
   *
   * @param string $message
   *   The message.
   *
   * @return $this
   *   The self.
   */
  public function setPaymentMethodUiMessage(string $message) : self {
    return $this->setValue('MSG_UI_PAYMENT_METHOD', $message);
  }

  /**
   * Sets the settlement message.
   *
   * Message to consumers bank statement or credit card bill if supported by
   * payment method.
   *
   * @param string $message
   *   The message.
   *
   * @return $this
   *   The self.
   */
  public function setPayerSettlementMessage(string $message) : self {
    return $this->setValue('MSG_SETTLEMENT_PAYER', $message);
  }

  /**
   * Sets the merchant settlement message.
   *
   * Message to merchants bank statement if supported by payment method.
   *
   * @param string $message
   *   The message.
   *
   * @return $this
   *   The self.
   */
  public function setMerchantSettlementMessage(string $message) : self {
    return $this->setValue('MSG_SETTLEMENT_MERCHANT', $message);
  }

  /**
   * Sets the locale.
   *
   * Currently supported: fi_FI, sv_SE, en_US.
   *
   * @param string $locale
   *   The locale.
   *
   * @return $this
   *   The self.
   */
  public function setLocale(string $locale) : self {
    Assert::oneOf($locale, ['fi_FI', 'sv_SE', 'en_US']);

    return $this->setValue('LOCALE', $locale);
  }

  /**
   * Sets the currency.
   *
   * Only EUR is supported currently.
   *
   * @param string $currency
   *   The currency.
   *
   * @return $this
   *   The self.
   */
  public function setCurrency(string $currency) : self {
    return $this->setValue('CURRENCY', $currency);
  }

  /**
   * Sets the reference number.
   *
   * The reference number in international RF format (e.g. 1232 or RF111232).
   *
   * @param string $number
   *   The reference number.
   *
   * @return $this
   *   The self.
   */
  public function setReferenceNumber(string $number) : self {
    return $this->setValue('REFERENCE_NUMBER', $number);
  }

  /**
   * Sets the visible payment methods.
   *
   * @param \Drupal\commerce_paytrail\Entity\PaymentMethod[] $methods
   *   The payment methods.
   *
   * @return $this
   *   The self.
   */
  public function setPaymentMethods(array $methods) : self {
    Assert::allIsInstanceOf($methods, PaymentMethod::class);

    return $this->setValue('PAYMENT_METHODS', implode(',', array_map(function (PaymentMethod $method) {
      return $method->id();
    }, $methods)));
  }

  /**
   * Populates the payer data.
   *
   * @param \Drupal\address\AddressInterface $address
   *   The address.
   *
   * @return $this
   *   The self.
   */
  public function setPayerFromAddress(AddressInterface $address) : self {
    return $this->setPayerAddress($address->getAddressLine1())
      ->setPayerCity($address->getLocality())
      ->setPayerFirstName($address->getGivenName())
      ->setPayerLastName($address->getFamilyName())
      ->setPayerCompany($address->getOrganization())
      ->setPayerPostalCode($address->getPostalCode())
      ->setPayerCountry($address->getCountryCode());
  }

  /**
   * Sets the phone number.
   *
   * @param string $phone
   *   The phone number.
   *
   * @return $this
   *   The self.
   */
  public function setPayerPhone(string $phone) : self {
    return $this->setValue('PAYER_PERSON_PHONE', $phone);
  }

  /**
   * Sets the email.
   *
   * @param string $email
   *   The email.
   *
   * @return $this
   *   The self.
   */
  public function setPayerEmail(string $email) : self {
    return $this->setValue('PAYER_PERSON_EMAIL', $email);
  }

  /**
   * Sets the first name.
   *
   * @param string $name
   *   The first name.
   *
   * @return $this
   *   The self.
   */
  public function setPayerFirstName(string $name) : self {
    return $this->setValue('PAYER_PERSON_FIRSTNAME', $name);
  }

  /**
   * Sets the last name.
   *
   * @param string $name
   *   The last name.
   *
   * @return $this
   *   The self.
   */
  public function setPayerLastName(string $name) : self {
    return $this->setValue('PAYER_PERSON_LASTNAME', $name);
  }

  /**
   * Sets the company.
   *
   * @param string $company
   *   The company.
   *
   * @return $this
   *   The self.
   */
  public function setPayerCompany(string $company) : self {
    return $this->setValue('PAYER_COMPANY_NAME', $company);
  }

  /**
   * Sets the address.
   *
   * @param string $address
   *   The address.
   *
   * @return $this
   *   The self.
   */
  public function setPayerAddress(string $address) : self {
    return $this->setValue('PAYER_PERSON_ADDR_STREET', $address);
  }

  /**
   * Sets the postal code.
   *
   * @param string $code
   *   The postal code.
   *
   * @return $this
   *   The self.
   */
  public function setPayerPostalCode(string $code) : self {
    return $this->setValue('PAYER_PERSON_ADDR_POSTAL_CODE', $code);
  }

  /**
   * Sets the city.
   *
   * @param string $city
   *   The city.
   *
   * @return $this
   *   The self.
   */
  public function setPayerCity(string $city) : self {
    return $this->setValue('PAYER_PERSON_ADDR_TOWN', $city);
  }

  /**
   * Sets the country.
   *
   * ISO 3166-2 country code.
   *
   * @param string $country
   *   The country code.
   *
   * @return $this
   *   The self.
   */
  public function setPayerCountry(string $country) : self {
    Assert::maxLength($country, 2);

    return $this->setValue('PAYER_PERSON_ADDR_COUNTRY', $country);
  }

  /**
   * Sets vat is included.
   *
   * Setting this to FALSE will remove the form element.
   *
   * @param bool $included
   *   The vat is included.
   *
   * @return $this
   *   The self.
   */
  public function setIsVatIncluded(bool $included) : self {
    if ($included) {
      return $this->setValue('VAT_IS_INCLUDED', '1');
    }
    return $this->removeValue('VAT_IS_INCLUDED');
  }

  /**
   * Sets the algorithm.
   *
   * Currently only '1' (sha256) is supported.
   *
   * @param int $alg
   *   The alg.
   *
   * @return $this
   *   The self.
   */
  public function setAlg(int $alg) : self {
    return $this->setValue('ALG', (string) $alg);
  }

  /**
   * Builds the key value array.
   *
   * @return array
   *   The key value array.
   */
  public function build() : array {
    $form = clone $this;

    foreach ($form->products as $i => $product) {
      // Remove total amount field if we deliver products.
      $form->removeValue('AMOUNT');

      foreach ($product->build($i) as $key => $value) {
        $form->setValue($key, $value);
      }
    }
    // Override params out because we don't support changing them
    // at the moment.
    $form->setParamsOut([
      'ORDER_NUMBER',
      'PAYMENT_ID',
      'PAYMENT_METHOD',
      'TIMESTAMP',
      'STATUS',
    ])
      // Update params in to contains all defines values.
      ->setParamsIn(array_map(function (FormValue $value) {
        return $value->key();
      }, $form->values));

    $values = [];
    foreach ($form->values as $key => $value) {
      $values[$value->key()] = $value->value();
    }

    return $values;
  }

}
