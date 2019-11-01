<?php

declare(strict_types = 1);

namespace Drupal\commerce_paytrail\Repository;

use CommerceGuys\Addressing\AddressInterface;
use Drupal\commerce_paytrail\AssertTrait;
use Drupal\commerce_paytrail\Entity\PaymentMethod;
use Drupal\commerce_paytrail\Repository\Product\Product;
use Drupal\commerce_paytrail\SanitizeTrait;
use Drupal\commerce_price\Price;
use Webmozart\Assert\Assert;

/**
 * Provides form interface base class.
 */
class FormManager extends BaseResource {

  use AssertTrait;
  use SanitizeTrait;

  /**
   * Array of paytrail products.
   *
   * @var \Drupal\commerce_paytrail\Repository\Product\Product[]
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

    $this->setMerchantId($merchant_id);
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
    $this->assertAmountBetween($amount, '0.65', '499999');

    return $this->setValue('AMOUNT', $amount, $this->formatPrice());
  }

  /**
   * Gets the amount.
   *
   * @return \Drupal\commerce_price\Price|null
   *   The amount.
   */
  public function getAmount() : ? Price {
    return $this->getValue('AMOUNT');
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
    Assert::numeric($id);
    Assert::maxLength($id, 11);

    return $this->setValue('MERCHANT_ID', $id);
  }

  /**
   * Gets the merchant id.
   *
   * @return string|null
   *   The merchant id.
   */
  public function getMerchantId() : ? string {
    return $this->getValue('MERCHANT_ID');
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
    $this->assertValidUrl($url);

    return $this->setValue('URL_SUCCESS', $url);
  }

  /**
   * Gets the success url.
   *
   * @return string|null
   *   The URL.
   */
  public function getSuccessUrl() : ? string {
    return $this->getValue('URL_SUCCESS');
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
    $this->assertValidUrl($url);

    return $this->setValue('URL_CANCEL', $url);
  }

  /**
   * Gets the cancel url.
   *
   * @return string|null
   *   The URL.
   */
  public function getCancelUrl() : ? string {
    return $this->getValue('URL_CANCEL');
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
    $this->assertValidUrl($url);

    return $this->setValue('URL_NOTIFY', $url);
  }

  /**
   * Gets the notify url.
   *
   * @return string|null
   *   The URL.
   */
  public function getNotifyUrl() : ? string {
    return $this->getValue('URL_NOTIFY');
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
    Assert::maxLength($orderNumber, 64);
    Assert::alnum($orderNumber);

    return $this->setValue('ORDER_NUMBER', $orderNumber);
  }

  /**
   * Gets the order number.
   *
   * @return string|null
   *   The order number.
   */
  public function getOrderNumber() : ? string {
    return $this->getValue('ORDER_NUMBER');
  }

  /**
   * Sets the products.
   *
   * @param \Drupal\commerce_paytrail\Repository\Product\Product[] $products
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
   * Gets the products.
   *
   * @return \Drupal\commerce_paytrail\Repository\Product\Product[]
   *   The products.
   */
  public function getProducts() : array {
    return $this->products;
  }

  /**
   * Sets the product.
   *
   * @param \Drupal\commerce_paytrail\Repository\Product\Product $product
   *   The product.
   *
   * @deprecated use addProduct() instead.
   *
   * @return $this
   *   The self.
   */
  public function setProduct(Product $product) : self {
    return $this->addProduct($product);
  }

  /**
   * Sets the product.
   *
   * @param \Drupal\commerce_paytrail\Repository\Product\Product $product
   *   The product.
   *
   * @return $this
   *   The self.
   */
  public function addProduct(Product $product) : self {
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
    Assert::maxLength($message, 255);

    return $this->setValue('MSG_UI_MERCHANT_PANEL', $message, function (string $string) {
      return $this->sanitizeTextStrict($string);
    });
  }

  /**
   * Gets the merchant panel ui message.
   *
   * @return string|null
   *   The message.
   */
  public function getMerchantPanelUiMessage() : ? string {
    return $this->getValue('MSG_UI_MERCHANT_PANEL');
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
    Assert::maxLength($message, 255);

    return $this->setValue('MSG_UI_PAYMENT_METHOD', $message, function (string $string) {
      return $this->sanitizeTextStrict($string);
    });
  }

  /**
   * Gets the payment method ui message.
   *
   * @return string|null
   *   The message.
   */
  public function getPaymentMethodUiMessage() : ? string {
    return $this->getValue('MSG_UI_PAYMENT_METHOD');
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
    Assert::maxLength($message, 255);

    return $this->setValue('MSG_SETTLEMENT_PAYER', $message, function (string $string) {
      return $this->sanitizeTextStrict($string);
    });
  }

  /**
   * Gets the payer settlement message.
   *
   * @return string|null
   *   The message.
   */
  public function getPayerSettlementMessage() : ? string {
    return $this->getValue('MSG_SETTLEMENT_PAYER');
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
    Assert::maxLength($message, 255);

    return $this->setValue('MSG_SETTLEMENT_MERCHANT', $message, function (string $string) {
      return $this->sanitizeTextStrict($string);
    });
  }

  /**
   * Gets the merchant settlement message.
   *
   * @return string|null
   *   The message.
   */
  public function getMerchantSettlementMessage() : ? string {
    return $this->getValue('MSG_SETTLEMENT_MERCHANT');
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
   * Gets the locale.
   *
   * @return string|null
   *   The locale.
   */
  public function getLocale() : ? string {
    return $this->getValue('LOCALE');
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
   * Gets the currency.
   *
   * @return string|null
   *   The currency.
   */
  public function getCurrency() : ? string {
    return $this->getValue('CURRENCY');
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
    Assert::maxLength($number, 20);
    Assert::alnum($number);

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

    $this->setValue('PAYMENT_METHODS', $methods, function (array $methods) {
      return implode(',', array_map(function (PaymentMethod $method) {
        return $method->id();
      }, $methods));
    });

    return $this;
  }

  /**
   * Gets the payment methods.
   *
   * @return \Drupal\commerce_paytrail\Entity\PaymentMethod[]
   *   The payment methods.
   */
  public function getPayementMethods() : array {
    return $this->getValue('PAYMENT_METHODS') ?? [];
  }

  /**
   * Populates the payer data.
   *
   * @param \CommerceGuys\Addressing\AddressInterface $address
   *   The address.
   *
   * @return $this
   *   The self.
   */
  public function setPayerFromAddress(AddressInterface $address) : self {
    if ($addr = $address->getAddressLine1()) {
      $this->setPayerAddress($addr);
    }
    if ($city = $address->getLocality()) {
      $this->setPayerCity($city);
    }
    if ($firstname = $address->getGivenName()) {
      $this->setPayerFirstName($firstname);
    }
    if ($lastname = $address->getFamilyName()) {
      $this->setPayerLastName($lastname);
    }
    if ($company = $address->getOrganization()) {
      $this->setPayerCompany($company);
    }
    if ($postal = $address->getPostalCode()) {
      $this->setPayerPostalCode($postal);
    }
    if ($country = $address->getCountryCode()) {
      $this->setPayerCountry($country);
    }
    return $this;
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
    $this->assertPhone($phone);

    return $this->setValue('PAYER_PERSON_PHONE', $phone);
  }

  /**
   * Gets the payer phone.
   *
   * @return string|null
   *   The payer phone.
   */
  public function getPayerPhone() : ? string {
    return $this->getValue('PAYER_PERSON_PHONE');
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
    Assert::maxLength($email, 255);

    return $this->setValue('PAYER_PERSON_EMAIL', $email);
  }

  /**
   * Gets the payer email.
   *
   * @return string|null
   *   The email.
   */
  public function getPayerEmail() : ? string {
    return $this->getValue('PAYER_PERSON_EMAIL');
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
    Assert::maxLength($name, 64);

    return $this->setValue('PAYER_PERSON_FIRSTNAME', $name, function (string $string) {
      return $this->sanitizeText($string);
    });
  }

  /**
   * Gets the payer first name.
   *
   * @return string|null
   *   The first name.
   */
  public function getPayerFirstName() : ? string {
    return $this->getValue('PAYER_PERSON_FIRSTNAME');
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
    Assert::maxLength($name, 64);

    return $this->setValue('PAYER_PERSON_LASTNAME', $name, function (string $string) {
      return $this->sanitizeText($string);
    });
  }

  /**
   * Gets the payer last name.
   *
   * @return string|null
   *   The last name.
   */
  public function getPayerLastName() : ? string {
    return $this->getValue('PAYER_PERSON_LASTNAME');
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
    Assert::maxLength($company, 128);

    return $this->setValue('PAYER_COMPANY_NAME', $company, function (string $string) {
      return $this->sanitizeText($string);
    });
  }

  /**
   * Gets the payer company name.
   *
   * @return string|null
   *   The company name.
   */
  public function getPayerCompany() : ? string {
    return $this->getValue('PAYER_COMPANY_NAME');
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
    Assert::maxLength($address, 128);

    return $this->setValue('PAYER_PERSON_ADDR_STREET', $address, function (string $string) {
      return $this->sanitizeText($string);
    });
  }

  /**
   * Gets the street address.
   *
   * @return string|null
   *   The street address.
   */
  public function getPayerAddress() : ? string {
    return $this->getValue('PAYER_PERSON_ADDR_STREET');
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
    $this->assertPostalCode($code);

    return $this->setValue('PAYER_PERSON_ADDR_POSTAL_CODE', $code);
  }

  /**
   * Gets the payer postal code.
   *
   * @return string|null
   *   The postal code.
   */
  public function getPayerPostalCode() : ? string {
    return $this->getValue('PAYER_PERSON_ADDR_POSTAL_CODE');
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
    Assert::maxLength($city, 64);

    return $this->setValue('PAYER_PERSON_ADDR_TOWN', $city, function (string $string) {
      return $this->sanitizeText($string);
    });
  }

  /**
   * Gets the payer city.
   *
   * @return string|null
   *   The payer city.
   */
  public function getPayerCity() : ? string {
    return $this->getValue('PAYER_PERSON_ADDR_TOWN');
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
   * Gets the payer country code.
   *
   * @return string|null
   *   The country code.
   */
  public function getPayerCountry() : ? string {
    return $this->getValue('PAYER_PERSON_ADDR_COUNTRY');
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
      return $this->setValue('VAT_IS_INCLUDED', 1);
    }
    return $this->removeValue('VAT_IS_INCLUDED');
  }

  /**
   * Whether VAT is included in prices or not.
   *
   * @return bool
   *   TRUE if vat is included, FALSE if not.
   */
  public function getIsVatIncluded() : bool {
    return $this->getValue('VAT_IS_INCLUDED') ? TRUE : FALSE;
  }

  /**
   * Sets the algorithm.
   *
   * Currently only '1' (sha256) is supported.
   *
   * @param string $alg
   *   The alg.
   *
   * @return $this
   *   The self.
   */
  public function setAlg(string $alg) : self {
    return $this->setValue('ALG', $alg);
  }

  /**
   * Gets the algorithm.
   *
   * @return string|null
   *   The used algorithm.
   */
  public function getAlg() : ? string {
    return $this->getValue('ALG');
  }

  /**
   * Builds the key value array.
   *
   * @return array
   *   The key value array.
   */
  public function build() : array {
    $form = clone $this;

    $values = [
      'PARAMS_IN' => '',
      'PARAMS_OUT' => implode(',', [
        'ORDER_NUMBER',
        'PAYMENT_ID',
        'PAYMENT_METHOD',
        'TIMESTAMP',
        'STATUS',
      ]),
    ];

    foreach ($form->values as $key => $item) {
      $values[$key] = $item->format();
    }

    foreach ($form->products as $i => $product) {
      // Remove total amount field if we deliver products.
      if (isset($values['AMOUNT'])) {
        unset($values['AMOUNT']);
      }

      foreach ($product->build() as $key => $value) {
        $values[sprintf('%s[%d]', $key, $i)] = $value;
      }
    }

    $values['PARAMS_IN'] = implode(',', array_keys($values));

    return $values;
  }

}
