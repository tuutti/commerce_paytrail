<?php

namespace Drupal\Tests\commerce_paytrail\Unit;

use Drupal\commerce_paytrail\Entity\PaymentMethod;
use Drupal\commerce_paytrail\Repository\FormManager;
use Drupal\commerce_paytrail\Repository\Product\Product;
use Drupal\commerce_price\Price;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * FormManager unit tests.
 *
 * @group commerce_paytrail
 * @coversDefaultClass \Drupal\commerce_paytrail\Repository\FormManager
 */
class FormManagerTest extends UnitTestCase {

  /**
   * The form manager.
   *
   * @var \Drupal\commerce_paytrail\Repository\FormManager
   */
  protected $sut;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    $this->sut = new FormManager('13466', '6pKF4jkv97zmqBJ3ZL8gUw5DfT2NMQ');
  }

  /**
   * Tests the default values.
   *
   * @covers ::build
   * @covers ::__construct
   */
  public function testDefaults() {
    $build = $this->sut->build();

    $expected = [
      'PARAMS_IN' => 'PARAMS_IN,PARAMS_OUT,MERCHANT_ID',
      'MERCHANT_ID' => '13466',
      'PARAMS_OUT' => 'ORDER_NUMBER,PAYMENT_ID,PAYMENT_METHOD,TIMESTAMP,STATUS',
    ];
    $this->assertEquals($expected, $build);
  }

  /**
   * Tests ::setPayerPhone().
   *
   * @covers ::setPayerPhone
   * @covers ::getPayerPhone
   * @dataProvider getInvalidPhoneNumbers
   */
  public function testInvalidPayerPhone(string $phoneNumber) {
    $this->expectException(\InvalidArgumentException::class);
    $this->assertValidPayerPhone($phoneNumber);
  }

  /**
   * Tests ::setPayerPhone().
   *
   * @covers ::setPayerPhone
   * @covers ::getPayerPhone
   * @dataProvider getValidPhoneNumbers
   */
  public function testValidPayerPhone(string $phoneNumber) {
    $this->assertValidPayerPhone($phoneNumber);
  }

  /**
   * Asserts valid phone number.
   *
   * @param string $phoneNumber
   *   The phone number.
   */
  private function assertValidPayerPhone(string $phoneNumber) : void {
    $this->sut->setPayerPhone($phoneNumber);
    $this->assertEquals($phoneNumber, $this->sut->getPayerPhone());
  }

  /**
   * Data provider for testInvalidPayerPhone.
   *
   * @return array
   *   An array of phone numbers.
   */
  public function getInvalidPhoneNumbers() : array {
    return [
      ['040213121_3123'],
      ['033213:231'],
      ['dsadsad0404'],
    ];
  }

  /**
   * Data provider for testValidPayerPhone.
   *
   * @return array
   *   An array of phone numbers.
   */
  public function getValidPhoneNumbers() : array {
    return [
      ['040123456'],
      ['+3584012345'],
    ];
  }

  /**
   * Tests ::assertPostalCode().
   *
   * @covers ::setPayerPostalCode
   * @covers ::getPayerPostalCode
   * @dataProvider getInvalidPostalCodes
   */
  public function testInvalidPostalCodes(string $code) {
    $this->expectException(\InvalidArgumentException::class);
    $this->assertPostalCode($code);
  }

  /**
   * Tests ::assertPostalCode().
   *
   * @covers ::setPayerPostalCode
   * @covers ::getPayerPostalCode
   * @dataProvider getValidPostalCodes
   */
  public function testValidPostalCodes(string $code) {
    $this->assertPostalCode($code);
  }

  /**
   * Asserts postal code.
   *
   * @param string $code
   *   The postal code.
   */
  private function assertPostalCode(string $code) : void {
    $this->sut->setPayerPostalCode($code);
    $this->assertEquals($code, $this->sut->getPayerPostalCode());
  }

  /**
   * Data provider for testAssertPostalCode().
   *
   * @return array
   *   The data set.
   */
  public function getInvalidPostalCodes() : array {
    return [
      ['wwww:dsd'],
      ['dasda//dsa'],
    ];
  }

  /**
   * Data provider for testAssertPostalCode().
   *
   * @return array
   *   The data set.
   */
  public function getValidPostalCodes() : array {
    return [
      ['CR2 6XH'],
      ['123456'],
      ['12345AW'],
      ['W134555A'],
      ['w123Wa'],
    ];
  }

  /**
   * Tests url validation.
   *
   * @covers ::setSuccessUrl
   * @covers ::setCancelUrl
   * @covers ::setNotifyUrl
   * @covers ::getSuccessUrl
   * @covers ::getCancelUrl
   * @covers ::getNotifyUrl
   * @dataProvider getValidUrlSets
   */
  public function testValidUrlsets(string $success, string $cancel, string $notify) {
    $this->assertUrlSet($success, $cancel, $notify);
  }

  /**
   * Tests url validation.
   *
   * @covers ::setSuccessUrl
   * @covers ::setCancelUrl
   * @covers ::setNotifyUrl
   * @covers ::getSuccessUrl
   * @covers ::getCancelUrl
   * @covers ::getNotifyUrl
   * @dataProvider getInvalidUrlSets
   */
  public function testInvalidUrlsets(string $success, string $cancel, string $notify) {
    $this->expectException(\InvalidArgumentException::class);
    $this->assertUrlSet($success, $cancel, $notify);
  }

  /**
   * Asserts the url set.
   *
   * @param string $success
   *   The success url.
   * @param string $cancel
   *   The cancel url.
   * @param string $notify
   *   The notify url.
   */
  private function assertUrlSet(string $success, string $cancel, string $notify) : void {
    $this->sut->setSuccessUrl($success)
      ->setCancelUrl($cancel)
      ->setNotifyUrl($notify);

    $this->assertEquals($success, $this->sut->getSuccessUrl());
    $this->assertEquals($cancel, $this->sut->getCancelUrl());
    $this->assertEquals($notify, $this->sut->getNotifyUrl());
  }

  /**
   * Data provider for testValidUrlsets().
   *
   * @return array
   *   The data.
   */
  public function getValidUrlSets() : array {
    return [
      ['http://localhost', 'http://localhost', 'http://localhost', FALSE],
    ];
  }

  /**
   * Data provider for testInvalidUrlsets().
   *
   * @return array
   *   The data.
   */
  public function getInvalidUrlSets() : array {
    return [
      ['http://', 'http://localhost', 'http://localhost'],
      ['http://localhost', 'http://', 'http://localhost'],
      ['http://localhost', 'http://localhost', 'http://'],
    ];
  }

  /**
   * Tests ::setAmount().
   *
   * @covers ::setAmount
   * @covers ::getAmount
   * @covers \Drupal\commerce_paytrail\AssertTrait::assertAmountBetween
   * @dataProvider getInvalidAmountData
   */
  public function testInvalidSetAmount(string $num) {
    $this->expectException(\InvalidArgumentException::class);
    $this->assertAmount($num);
  }

  /**
   * Tests ::setAmount().
   *
   * @covers ::setAmount
   * @covers ::getAmount
   * @covers \Drupal\commerce_paytrail\AssertTrait::assertAmountBetween
   * @dataProvider getValidAmountData
   */
  public function testValidSetAmount(string $num) {
    $this->assertAmount($num);
  }

  /**
   * Asserts amount.
   *
   * @param string $num
   *   The amount.
   */
  private function assertAmount(string $num) : void {
    $price = new Price($num, 'EUR');
    $this->sut->setAmount($price);
    $this->assertEquals($price, $this->sut->getAmount());
  }

  /**
   * Provides assert between data.
   *
   * @return array
   *   The data.
   */
  public function getInvalidAmountData() {
    return [
      ['500000'],
      ['0.64'],
    ];
  }

  /**
   * Provides assert between data.
   *
   * @return array
   *   The data.
   */
  public function getValidAmountData() {
    return [
      ['499999'],
      ['0.65'],
    ];
  }

  /**
   * Tests build with all available values.
   *
   * @covers ::build
   * @covers ::setAmount
   * @covers ::setMerchantId
   * @covers ::setSuccessUrl
   * @covers ::setCancelUrl
   * @covers ::setNotifyUrl
   * @covers ::setOrderNumber
   * @covers ::setMerchantPanelUiMessage
   * @covers ::setPaymentMethodUiMessage
   * @covers ::setPayerSettlementMessage
   * @covers ::setMerchantSettlementMessage
   * @covers ::setLocale
   * @covers ::setCurrency
   * @covers ::setReferenceNumber
   * @covers ::setPaymentMethods
   * @covers ::setPayerPhone
   * @covers ::setPayerEmail
   * @covers ::setPayerFirstName
   * @covers ::setPayerLastName
   * @covers ::setPayerCompany
   * @covers ::setPayerAddress
   * @covers ::setPayerPostalCode
   * @covers ::setPayerCity
   * @covers ::setPayerCountry
   * @covers ::setIsVatIncluded
   * @covers ::setAlg
   * @covers ::removeValue
   * @covers ::setValue
   * @covers \Drupal\commerce_paytrail\Entity\PaymentMethod
   */
  public function testBuild() {
    $entityManager = $this->getMockBuilder(EntityTypeManagerInterface::class)
      ->getMock();
    $container = new ContainerBuilder();
    $container->set('entity.manager', $entityManager);

    \Drupal::setContainer($container);

    $entities = [
      new PaymentMethod([
        'id' => '1',
        'label' => 'Label',
      ], 'paytrail_payment_method'),
      new PaymentMethod([
        'id' => '2',
        'label' => 'Label',
      ], 'paytrail_payment_method'),
    ];

    $this->sut->setAmount(new Price('123', 'EUR'))
      ->setMerchantId('12345')
      ->setSuccessUrl('http://localhost/success')
      ->setCancelUrl('http://localhost/cancel')
      ->setNotifyUrl('http://localhost/notify')
      ->setOrderNumber('123')
      ->setLocale('fi_FI')
      ->setCurrency('EUR')
      ->setReferenceNumber('234')
      ->setPaymentMethods($entities)
      ->setPayerPhone('040 123456')
      ->setPayerEmail('test@localhost')
      // Add random invalid characters to make sure text sanitization
      // works as expected.
      ->setPayerFirstName('Firstname<>%€')
      ->setPayerLastName('Lastname<>%')
      ->setPayerCompany('Company<>%€')
      ->setPayerAddress('Street 1')
      ->setPayerPostalCode('00100')
      ->setMerchantPanelUiMessage('merchant_message<>%€')
      ->setPaymentMethodUiMessage('payment_ui_message<>%€')
      ->setPayerSettlementMessage('payment_settlement_message<>%€')
      ->setMerchantSettlementMessage('merchant_settlement_message<>%€')
      ->setPayerCity('Helsinki')
      ->setPayerCountry('FI')
      ->setIsVatIncluded(TRUE)
      ->setAlg(1);

    $expected = [
      'PARAMS_IN' => 'PARAMS_IN,PARAMS_OUT,MERCHANT_ID,AMOUNT,URL_SUCCESS,URL_CANCEL,URL_NOTIFY,ORDER_NUMBER,LOCALE,CURRENCY,REFERENCE_NUMBER,PAYMENT_METHODS,PAYER_PERSON_PHONE,PAYER_PERSON_EMAIL,PAYER_PERSON_FIRSTNAME,PAYER_PERSON_LASTNAME,PAYER_COMPANY_NAME,PAYER_PERSON_ADDR_STREET,PAYER_PERSON_ADDR_POSTAL_CODE,MSG_UI_MERCHANT_PANEL,MSG_UI_PAYMENT_METHOD,MSG_SETTLEMENT_PAYER,MSG_SETTLEMENT_MERCHANT,PAYER_PERSON_ADDR_TOWN,PAYER_PERSON_ADDR_COUNTRY,VAT_IS_INCLUDED,ALG',
      'MERCHANT_ID' => '12345',
      'PARAMS_OUT' => 'ORDER_NUMBER,PAYMENT_ID,PAYMENT_METHOD,TIMESTAMP,STATUS',
      'AMOUNT' => '123.00',
      'URL_SUCCESS' => 'http://localhost/success',
      'URL_CANCEL' => 'http://localhost/cancel',
      'URL_NOTIFY' => 'http://localhost/notify',
      'ORDER_NUMBER' => '123',
      'MSG_UI_MERCHANT_PANEL' => 'merchant_message',
      'MSG_UI_PAYMENT_METHOD' => 'payment_ui_message',
      'MSG_SETTLEMENT_PAYER' => 'payment_settlement_message',
      'MSG_SETTLEMENT_MERCHANT' => 'merchant_settlement_message',
      'LOCALE' => 'fi_FI',
      'CURRENCY' => 'EUR',
      'REFERENCE_NUMBER' => '234',
      'PAYMENT_METHODS' => '1,2',
      'PAYER_PERSON_PHONE' => '040 123456',
      'PAYER_PERSON_EMAIL' => 'test@localhost',
      'PAYER_PERSON_FIRSTNAME' => 'Firstname',
      'PAYER_PERSON_LASTNAME' => 'Lastname',
      'PAYER_COMPANY_NAME' => 'Company',
      'PAYER_PERSON_ADDR_STREET' => 'Street 1',
      'PAYER_PERSON_ADDR_POSTAL_CODE' => '00100',
      'PAYER_PERSON_ADDR_TOWN' => 'Helsinki',
      'PAYER_PERSON_ADDR_COUNTRY' => 'FI',
      'VAT_IS_INCLUDED' => '1',
      'ALG' => '1',
    ];

    $this->assertEquals($expected, $this->sut->build());

    // Make sure setting vat is included removes the value from params in.
    $this->sut->setIsVatIncluded(FALSE);

    unset($expected['VAT_IS_INCLUDED']);
    $expected['PARAMS_IN'] = str_replace('VAT_IS_INCLUDED,', '', $expected['PARAMS_IN']);

    $this->assertEquals($expected, $this->sut->build());
  }

  /**
   * Tests setProduct() methods.
   *
   * @covers ::setProduct
   * @covers ::setProducts
   */
  public function testProducts() {
    $product = (new Product())
      ->setTitle('Title<>€%')
      ->setItemId('1')
      ->setQuantity(1)
      ->setDiscount(1.5)
      ->setPrice(new Price('11', 'EUR'));

    $product2 = (new Product())
      ->setTitle('Title 2<>€%')
      ->setItemId('2')
      ->setQuantity(1)
      ->setDiscount(1.5)
      ->setPrice(new Price('23', 'EUR'));

    $this->sut->setAmount(new Price('123', 'EUR'))
      ->addProduct($product);

    $expected = [
      'PARAMS_IN' => 'PARAMS_IN,PARAMS_OUT,MERCHANT_ID,ITEM_TYPE[0],ITEM_TITLE[0],ITEM_ID[0],ITEM_QUANTITY[0],ITEM_DISCOUNT_PERCENT[0],ITEM_UNIT_PRICE[0]',
      'MERCHANT_ID' => '13466',
      'PARAMS_OUT' => 'ORDER_NUMBER,PAYMENT_ID,PAYMENT_METHOD,TIMESTAMP,STATUS',
      'ITEM_ID[0]' => '1',
      'ITEM_TITLE[0]' => 'Title',
      'ITEM_QUANTITY[0]' => '1',
      'ITEM_TYPE[0]' => '1',
      'ITEM_DISCOUNT_PERCENT[0]' => '1.5',
      'ITEM_UNIT_PRICE[0]' => '11.00',
    ];
    $this->assertEquals($expected, $this->sut->build());

    // Make sure we can override products.
    $this->sut->setProducts([$product2]);

    $expected['ITEM_TITLE[0]'] = 'Title 2';
    $expected['ITEM_UNIT_PRICE[0]'] = '23.00';
    $expected['ITEM_ID[0]'] = '2';
    $this->assertEquals($expected, $this->sut->build());
  }

  /**
   * Tests generateReturnChecksum() method.
   *
   * @covers ::generateAuthCode
   * @dataProvider generateReturnChecksumProvider
   */
  public function testGenerateReturnChecksum($values, $expected) {
    $return = $this->sut->generateReturnChecksum($values);
    $this->assertEquals($return, $expected);
  }

  /**
   * Data provider for testGenerateReturnChecksum().
   */
  public function generateReturnChecksumProvider() {
    return [
      [
        [1, 2, 3, 4],
        '6A14387E77136D78A1859AE508E4642EAA82BD66A3F46485795D8D61211B4F14',
      ],
      [
        ['s' => '123', 'd' => '22'],
        '1DD0B2B1FD4F2816AFA00AA33E97ABBFD1C3BEE14438F932B240D0A59564C03F',
      ],
    ];
  }

  /**
   * Tests generateAuthCode() method.
   *
   * @covers ::generateAuthCode
   * @dataProvider generateAuthCodeProvider
   */
  public function testGenerateAuthCode($values, $expected) {
    $return = $this->sut->generateAuthCode($values);
    $this->assertEquals($return, $expected);
  }

  /**
   * Data provider for testGenerateAuthCode().
   */
  public function generateAuthCodeProvider() {
    return [
      [
        [
          'test' => 1,
          'test2' => '233',
          'value' => 'jo0',
        ],
        'CBAF6742675ABB962EF56327862838CDBFDA92F9F71FC29E08721AFAC0526856',
      ],
      [
        [1, 2, 3, 4, 5],
        'D732E2333E6E3F0F355EC7FC54EA2DF72E5184D12AB696CED7CFAE99636343A8',
      ],
    ];
  }

}
