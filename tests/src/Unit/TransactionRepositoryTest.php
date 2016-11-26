<?php

namespace Drupal\Tests\commerce_paytrail\Unit;

use Drupal\commerce_order\Entity\OrderItem;
use Drupal\commerce_paytrail\Plugin\Commerce\PaymentGateway\Paytrail;
use Drupal\commerce_paytrail\Repository\TransactionRepository;
use Drupal\commerce_paytrail\Repository\TransactionValue;
use Drupal\commerce_price\Price;
use Drupal\Tests\UnitTestCase;

/**
 * TransactionRepository unit tests.
 *
 * @group commerce_paytrail
 * @coversDefaultClass \Drupal\commerce_paytrail\Repository\TransactionRepository
 */
class TransactionRepositoryTest extends UnitTestCase {

  /**
   * The mocked order item.
   *
   * @var \Drupal\commerce_order\Entity\OrderItem
   */
  protected $orderItem;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    $this->orderItem = $this->getMockBuilder(OrderItem::class)
      ->disableOriginalConstructor()
      ->getMock();

    $this->orderItem->expects($this->any())
      ->method('getTotalPrice')
      ->will($this->returnValue(new Price(666, 'USD')));
    $this->orderItem->expects($this->any())
      ->method('getTitle')
      ->will($this->returnValue('Test product title'));
    $this->orderItem->expects($this->any())
      ->method('getQuantity')
      ->will($this->returnValue(1));
  }

  /**
   * Tests build() method.
   *
   * @covers ::build
   * @covers ::set
   * @covers ::get
   * @covers ::getBuildOrder
   * @covers ::build
   * @covers ::setItems
   * @covers ::setIncludeVat
   * @covers ::setContactCountry
   * @covers ::setContactCity
   * @covers ::setContactZip
   * @covers ::setContactAddress
   * @covers ::setContactCompany
   * @covers ::setContactName
   * @covers ::setContactEmail
   * @covers ::setContactCellno
   * @covers ::setContactTelno
   * @covers ::setGroup
   * @covers ::setVisibleMethods
   * @covers ::setMode
   * @covers ::setPreselectedMethod
   * @covers ::setCulture
   * @covers ::setType
   * @covers ::setReturnAddress
   * @covers ::setCurrency
   * @covers ::setOrderDescription
   * @covers ::setReferenceNumber
   * @covers ::setOrderNumber
   * @covers ::setAmount
   * @covers ::setMerchantId
   * @covers ::setProduct
   * @covers ::raw
   * @dataProvider buildDataProvider
   */
  public function testBuild($given, $expected) {
    $repo = $this->getRepository($given);

    $response = $repo->build();
    $this->assertEquals($response, $expected);
  }

  /**
   * Tests build() exceptions.
   *
   * @covers ::build
   * @dataProvider buildDataProviderExceptions
   */
  public function testBuildExceptions($given, $message) {
    $repo = $this->getRepository($given);

    try {
      $repo->build();
    }
    catch (\InvalidArgumentException $e) {
      $this->assertEquals($e->getMessage(), $message);
      return;
    }
    $this->fail('Failed to assert required exceptions');
  }

  /**
   * Tests TransactionValue.
   *
   * @covers \Drupal\commerce_paytrail\Repository\TransactionValue::__construct
   * @covers \Drupal\commerce_paytrail\Repository\TransactionValue::value
   * @covers \Drupal\commerce_paytrail\Repository\TransactionValue::passRequirements
   * @covers \Drupal\commerce_paytrail\Repository\TransactionValue::matches
   */
  public function testTransactionValue() {
    $value = new TransactionValue('test', TRUE, 'S1');
    $this->assertTrue($value->passRequirements());

    $value = new TransactionValue(NULL, TRUE, 'S1');
    $this->assertFalse($value->passRequirements());

    $value = new TransactionValue('Cat', TRUE, 'S1');
    $this->assertTrue($value->matches('S1'));
    $this->assertFalse($value->matches('E1'));

    $value = new TransactionValue('Cat', TRUE, NULL);
    $this->assertTrue($value->matches('E1'));
    $this->assertTrue($value->matches('S1'));

    $this->assertEquals($value->value(), 'Cat');
  }

  /**
   * Data provider for testBuildExceptions.
   */
  public function buildDataProviderExceptions() {
    $data = $this->buildDataProvider()[0];

    list ($base,) = $data;

    return [
      // Test missing value.
      [
        array_merge($base, [
          'merchant_id' => NULL,
        ]),
        'merchant_id is marked as required and is missing a value.',
      ],
      // Test incorrect products number.
      [
        array_merge($base, [
          'items' => 2,
          'products' => 1,
        ]),
        'Given item count does not match the actual product count.',
      ],
    ];
  }

  /**
   * Test data for testBuild().
   *
   * @return array
   *   Test data.
   */
  public function buildDataProvider() {
    $base = [
      'merchant_id' => 'merchantid123',
      'amount' => new Price(666, 'USD'),
      'order_number' => 666,
      'reference_number' => '12321',
      'order_description' => 'Test description',
      'currency' => 'EUR',
      'return_address' => 'http://localhost/return',
      'cancel_address' => 'http://localhost/cancel',
      'notify_address' => 'http://localhost/notify',
      'pending_address' => 'http://localhost/notify',
      'type' => 'S1',
      'mode' => Paytrail::BYPASS_MODE,
      'culture' => 'en_US',
      'preselected_method' => '',
      'visible_methods' => [],
      'group' => '',
      'contact_tellno' => '123456',
      'contact_cellno' => '123456',
      'contact_email' => 'test@localhost',
      'contact_name' => 'Firstname Lastname',
      'contact_company' => 'company test',
      'contact_addr_street' => 'Test street',
      'contact_addr_zip' => '00100',
      'contact_addr_city' => 'Helsinki',
      'contact_addr_country' => 'FI',
      'include_vat' => 1,
      'items' => 0,
    ];
    $base_expected = [
      'MERCHANT_ID' => 'merchantid123',
      'ORDER_NUMBER' => 666,
      'REFERENCE_NUMBER' => '12321',
      'ORDER_DESCRIPTION' => 'Test description',
      'CURRENCY' => 'EUR',
      'RETURN_ADDRESS' => 'http://localhost/return',
      'CANCEL_ADDRESS' => 'http://localhost/cancel',
      'NOTIFY_ADDRESS' => 'http://localhost/notify',
      'PENDING_ADDRESS' => 'http://localhost/notify',
      'TYPE' => 'S1',
      'MODE' => 2,
      'CULTURE' => 'en_US',
      'PRESELECTED_METHOD' => '',
      'VISIBLE_METHODS' => '',
      'GROUP' => '',
    ];
    $base_e1_expected = [
      'TYPE' => 'E1',
      'CONTACT_TELLNO' => '123456',
      'CONTACT_CELLNO' => '123456',
      'CONTACT_EMAIL' => 'test@localhost',
      'CONTACT_FIRSTNAME' => 'Firstname',
      'CONTACT_LASTNAME' => 'Lastname',
      'CONTACT_COMPANY' => 'company test',
      'CONTACT_ADDR_STREET' => 'Test street',
      'CONTACT_ADDR_ZIP' => '00100',
      'CONTACT_ADDR_CITY' => 'Helsinki',
      'CONTACT_ADDR_COUNTRY' => 'FI',
      'INCLUDE_VAT' => 1,
      'ITEMS' => 0,
    ];
    return [
      // Test S1.
      [$base, $base_expected + ['AMOUNT' => '666.00']],
      // Test E1.
      [
        ['type' => 'E1'] + $base,
        $base_e1_expected + $base_expected,
      ],
      // Test name conversion.
      [
        ['type' => 'E1', 'contact_name' => 'firstname'] + $base,
        [
          'CONTACT_FIRSTNAME' => 'firstname',
          'CONTACT_LASTNAME' => 'firstname',
        ] + $base_e1_expected + $base_expected,
      ],
      // Test product setting.
      [
        [
          'type' => 'E1',
          'items' => 2,
          'products' => 2,
        ] + $base,
        [
          'ITEMS' => 2,
          'ITEM_TITLE[0]' => 'Test product title',
          'ITEM_NO[0]' => '',
          'ITEM_AMOUNT[0]' => '1',
          'ITEM_PRICE[0]' => '666.00',
          'ITEM_TAX[0]' => '0.00',
          'ITEM_DISCOUNT[0]' => 0,
          'ITEM_TYPE[0]' => 1,
          'ITEM_TITLE[1]' => 'Test product title',
          'ITEM_NO[1]' => '',
          'ITEM_AMOUNT[1]' => '1',
          'ITEM_PRICE[1]' => '666.00',
          'ITEM_TAX[1]' => '0.00',
          'ITEM_DISCOUNT[1]' => 0,
          'ITEM_TYPE[1]' => 1,
        ] + $base_e1_expected + $base_expected,
      ],
    ];
  }

  /**
   * Get repository object.
   *
   * @param array $given
   *   List of initial parameters.
   *
   * @return \Drupal\commerce_paytrail\Repository\TransactionRepository
   *   The repository.
   */
  protected function getRepository($given) {
    $repo = new TransactionRepository();
    $repo->setMerchantId($given['merchant_id'])
      ->setAmount($given['amount'])
      ->setOrderNumber($given['order_number'])
      ->setReferenceNumber($given['reference_number'])
      ->setOrderDescription($given['order_description'])
      ->setCurrency($given['currency'])
      ->setReturnAddress('return', $given['return_address'])
      ->setReturnAddress('cancel', $given['cancel_address'])
      ->setReturnAddress('notify', $given['notify_address'])
      ->setReturnAddress('pending', $given['pending_address'])
      ->setType($given['type'])
      ->setCulture($given['culture'])
      ->setPreselectedMethod($given['preselected_method'])
      ->setMode($given['mode'])
      ->setVisibleMethods($given['visible_methods'])
      ->setGroup($given['group'])
      ->setContactTelno($given['contact_tellno'])
      ->setContactCellno($given['contact_cellno'])
      ->setContactEmail($given['contact_email'])
      ->setContactName($given['contact_name'])
      ->setContactCompany($given['contact_company'])
      ->setContactAddress($given['contact_addr_street'])
      ->setContactZip($given['contact_addr_zip'])
      ->setContactCity($given['contact_addr_city'])
      ->setContactCountry($given['contact_addr_country'])
      ->setIncludeVat($given['include_vat'])
      ->setItems($given['items']);

    if (!empty($given['products'])) {
      for ($i = 0; $i < $given['products']; $i++) {
        $repo->setProduct(clone $this->orderItem);
      }
    }

    return $repo;
  }

}
