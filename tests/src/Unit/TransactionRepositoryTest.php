<?php

namespace Drupal\Tests\commerce_paytrail\Unit;

use Drupal\commerce_order\Entity\OrderItem;
use Drupal\commerce_paytrail\Plugin\Commerce\PaymentGateway\Paytrail;
use Drupal\commerce_paytrail\Repository\EnterpriseTransactionRepository;
use Drupal\commerce_paytrail\Repository\SimpleTransactionRepository;
use Drupal\commerce_paytrail\Repository\TransactionRepository;
use Drupal\commerce_paytrail\Repository\TransactionValue;
use Drupal\commerce_price\Price;
use Drupal\Tests\UnitTestCase;

/**
 * TransactionRepository unit tests.
 *
 * @group commerce_paytrail
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
   * @covers \Drupal\commerce_paytrail\Repository\TransactionRepository::
   * @covers \Drupal\commerce_paytrail\Repository\EnterpriseTransactionRepository::
   * @dataProvider buildE1DataProvider
   */
  public function testE1Build($given, $expected) {
    $repo = $this->getRepository(new EnterpriseTransactionRepository(), $given);

    $response = $repo->build();
    $this->assertEquals($expected, $response);
  }

  /**
   * Data provider for testE1Build().
   */
  public function buildE1DataProvider() {
    return [
      [
        // Test that default values gets populated.
        [
          'merchant_id' => '123456',
          'currency' => 'EUR',
          'order_number' => '12345',
          'return_address' => 'http://localhost/return',
          'cancel_address' => 'http://localhost/cancel',
          'notify_address' => 'http://localhost/notify',
          'pending_address' => 'http://localhost/notify',
          'mode' => 2,
          'culture' => 'en_US',
          'preselected_method' => '',
          'visible_methods' => [],
          'contact_name' => 'Firstname Lastname',
          'contact_email' => 'test@email.fi',
          'contact_company' => 'company test',
          'contact_addr_street' => 'Test street',
          'contact_addr_zip' => '00100',
          'contact_addr_city' => 'Helsinki',
          'contact_addr_country' => 'FI',
          'include_vat' => 1,
          'items' => 1,
          'products' => 1,
        ],
        [
          'MERCHANT_ID' => '123456',
          'ORDER_NUMBER' => '12345',
          'REFERENCE_NUMBER' => '',
          'ORDER_DESCRIPTION' => '',
          'CURRENCY' => 'EUR',
          'RETURN_ADDRESS' => 'http://localhost/return',
          'CANCEL_ADDRESS' => 'http://localhost/cancel',
          'NOTIFY_ADDRESS' => 'http://localhost/notify',
          'PENDING_ADDRESS' => 'http://localhost/notify',
          'TYPE' => 'E1',
          'MODE' => 2,
          'CULTURE' => 'en_US',
          'PRESELECTED_METHOD' => '',
          'VISIBLE_METHODS' => '',
          'GROUP' => '',
          'CONTACT_TELLNO' => '',
          'CONTACT_CELLNO' => '',
          'CONTACT_EMAIL' => 'test@email.fi',
          'CONTACT_FIRSTNAME' => 'Firstname',
          'CONTACT_LASTNAME' => 'Lastname',
          'CONTACT_COMPANY' => 'company test',
          'CONTACT_ADDR_STREET' => 'Test street',
          'CONTACT_ADDR_ZIP' => '00100',
          'CONTACT_ADDR_CITY' => 'Helsinki',
          'CONTACT_ADDR_COUNTRY' => 'FI',
          'INCLUDE_VAT' => 1,
          'ITEMS' => 1,
          'ITEM_TITLE[0]' => 'Test product title',
          'ITEM_NO[0]' => '',
          'ITEM_AMOUNT[0]' => 1,
          'ITEM_PRICE[0]' => '666.00',
          'ITEM_TAX[0]' => '0.00',
          'ITEM_DISCOUNT[0]' => 0,
          'ITEM_TYPE[0]' => 1,
        ],
      ],
      // Test that all values gets populated.
      [
        [
          'merchant_id' => '123456',
          'currency' => 'EUR',
          'order_number' => '12345',
          'reference_number' => '12345',
          'order_description' => 'Order description',
          'return_address' => 'http://localhost/return',
          'cancel_address' => 'http://localhost/cancel',
          'notify_address' => 'http://localhost/notify',
          'pending_address' => 'http://localhost/notify',
          'mode' => 2,
          'culture' => 'en_US',
          'preselected_method' => '',
          'visible_methods' => [],
          'contact_name' => 'Firstname Lastname',
          'contact_tellno' => '123456',
          'contact_cellno' => '654321',
          'contact_email' => 'test@email.fi',
          'contact_company' => 'company test',
          'contact_addr_street' => 'Test street',
          'contact_addr_zip' => '00100',
          'contact_addr_city' => 'Helsinki',
          'contact_addr_country' => 'FI',
          'include_vat' => 1,
          'group' => '123',
          'items' => 1,
          'products' => 1,
        ],
        [
          'MERCHANT_ID' => '123456',
          'ORDER_NUMBER' => '12345',
          'REFERENCE_NUMBER' => '12345',
          'ORDER_DESCRIPTION' => 'Order description',
          'CURRENCY' => 'EUR',
          'RETURN_ADDRESS' => 'http://localhost/return',
          'CANCEL_ADDRESS' => 'http://localhost/cancel',
          'NOTIFY_ADDRESS' => 'http://localhost/notify',
          'PENDING_ADDRESS' => 'http://localhost/notify',
          'TYPE' => 'E1',
          'MODE' => 2,
          'CULTURE' => 'en_US',
          'PRESELECTED_METHOD' => '',
          'VISIBLE_METHODS' => '',
          'GROUP' => '123',
          'CONTACT_TELLNO' => '123456',
          'CONTACT_CELLNO' => '654321',
          'CONTACT_EMAIL' => 'test@email.fi',
          'CONTACT_FIRSTNAME' => 'Firstname',
          'CONTACT_LASTNAME' => 'Lastname',
          'CONTACT_COMPANY' => 'company test',
          'CONTACT_ADDR_STREET' => 'Test street',
          'CONTACT_ADDR_ZIP' => '00100',
          'CONTACT_ADDR_CITY' => 'Helsinki',
          'CONTACT_ADDR_COUNTRY' => 'FI',
          'INCLUDE_VAT' => 1,
          'ITEMS' => 1,
          'ITEM_TITLE[0]' => 'Test product title',
          'ITEM_NO[0]' => '',
          'ITEM_AMOUNT[0]' => 1,
          'ITEM_PRICE[0]' => '666.00',
          'ITEM_TAX[0]' => '0.00',
          'ITEM_DISCOUNT[0]' => 0,
          'ITEM_TYPE[0]' => 1,
        ],
      ],
    ];
  }

  /**
   * Tests TransactionValue.
   *
   * @covers \Drupal\commerce_paytrail\Repository\TransactionValue::
   */
  public function testTransactionValue() {
    $value = new TransactionValue('test', [
      '#required' => TRUE,
    ]);
    $this->assertTrue($value->passRequirements());

    $value = new TransactionValue(NULL, [
      '#required' => TRUE,
    ]);
    $this->assertFalse($value->passRequirements());

    $value = new TransactionValue('Cat', [
      '#required' => TRUE,
    ]);
    $this->assertEquals($value->value(), 'Cat');
  }

  /**
   * Get repository object.
   *
   * @param \Drupal\commerce_paytrail\Repository\TransactionRepository $repo
   *   The repository type.
   * @param array $given
   *   List of initial parameters.
   *
   * @return \Drupal\commerce_paytrail\Repository\TransactionRepository
   *   The repository.
   */
  protected function getRepository(TransactionRepository $repo, $given) {
    if ($repo instanceof EnterpriseTransactionRepository) {
      /** @var EnterpriseTransactionRepository $repo */
      if (isset($given['contact_tellno'])) {
        $repo->setContactTelno($given['contact_tellno']);
      }
      if (isset($given['contact_cellno'])) {
        $repo->setContactCellno($given['contact_cellno']);
      }
      $repo->setContactName($given['contact_name'])
        ->setContactEmail($given['contact_email'])
        ->setContactCompany($given['contact_company'])
        ->setContactAddress($given['contact_addr_street'])
        ->setContactZip($given['contact_addr_zip'])
        ->setContactCity($given['contact_addr_city'])
        ->setContactCountry($given['contact_addr_country'])
        ->setIncludeVat($given['include_vat'])
        ->setItems($given['items']);

      for ($i = 0; $i < $given['products']; $i++) {
        $repo->setProduct(clone $this->orderItem);
      }
    }
    else {
      /** @var SimpleTransactionRepository $repo */
      $repo->setAmount($given['amount']);
    }
    $repo->setMerchantId($given['merchant_id'])
      ->setOrderNumber($given['order_number'])
      ->setCurrency($given['currency'])
      ->setReturnAddress($given['return_address'])
      ->setCancelAddress($given['cancel_address'])
      ->setNotifyAddress($given['notify_address'])
      ->setPendingAddress($given['pending_address'])
      ->setCulture($given['culture'])
      ->setPreselectedMethod($given['preselected_method'])
      ->setMode($given['mode'])
      ->setVisibleMethods($given['visible_methods']);

    if (isset($given['group'])) {
      $repo->setGroup($given['group']);
    }
    if (isset($given['order_description'])) {
      $repo->setOrderDescription($given['order_description']);
    }
    if (isset($given['reference_number'])) {
      $repo->setReferenceNumber($given['reference_number']);
    }

    return $repo;
  }

}
