<?php

namespace Drupal\Tests\commerce_paytrail\Unit;

use Drupal\commerce_paytrail\Plugin\Commerce\PaymentGateway\PaytrailBase;
use Drupal\Core\Language\Language;
use Drupal\Tests\UnitTestCase;

/**
 * PaytrailBaseTest unit tests.
 *
 * @group commerce_paytrail
 * @coversDefaultClass \Drupal\commerce_paytrail\Plugin\Commerce\PaymentGateway\PaytrailBase
 */
class PaytrailBaseTest extends UnitTestCase {

  /**
   * The entity type manager.
   *
   * @var \PHPUnit_Framework_MockObject_MockObject|\Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The payment type manager.
   *
   * @var \PHPUnit_Framework_MockObject_MockObject|\Drupal\commerce_payment\PaymentTypeManager
   */
  protected $paymentTypeManager;

  /**
   * The payment method type manager.
   *
   * @var \PHPUnit_Framework_MockObject_MockObject|\Drupal\commerce_payment\PaymentMethodTypeManager
   */
  protected $paymentMethodTypeManager;

  /**
   * The language manager.
   *
   * @var \PHPUnit_Framework_MockObject_MockObject|\Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The paytrail payment manager.
   *
   * @var \PHPUnit_Framework_MockObject_MockObject|\Drupal\commerce_paytrail\PaymentManagerInterface
   */
  protected $paytrailPaymentManager;

  /**
   * The logger.
   *
   * @var \PHPUnit_Framework_MockObject_MockObject|\Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The paytrail base.
   *
   * @var \Drupal\commerce_paytrail\Plugin\Commerce\PaymentGateway\PaytrailBase
   */
  protected $sut;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    $this->entityTypeManager = $this->getMock('\Drupal\Core\Entity\EntityTypeManagerInterface');
    $this->paymentTypeManager = $this->getMockBuilder('\Drupal\commerce_payment\PaymentTypeManager')
      ->disableOriginalConstructor()
      ->getMock();
    $this->paymentMethodTypeManager = $this->getMockBuilder('\Drupal\commerce_payment\PaymentMethodTypeManager')
      ->disableOriginalConstructor()
      ->getMock();
    $this->languageManager = $this->getMock('\Drupal\Core\Language\LanguageManagerInterface');
    $this->paytrailPaymentManager = $this->getMock('\Drupal\commerce_paytrail\PaymentManagerInterface');
    $this->logger = $this->getMock('\Psr\Log\LoggerInterface');

    $config = [
      '_entity_id' => 123,
      'payment_type' => 'default',
    ];
    $plugin = [
      'payment_type' => 'default',
      'payment_method_types' => [],
      'forms' => [],
      'modes' => [],
    ];
    $this->sut = new PaytrailBase($config, '', $plugin, $this->entityTypeManager, $this->paymentTypeManager, $this->paymentMethodTypeManager, $this->languageManager, $this->paytrailPaymentManager, $this->logger);
  }

  /**
   * Make sure test mode fallbacks to test credentials.
   *
   * @covers ::getMerchantId
   * @covers ::getMerchantHash
   * @covers ::getSetting
   * @covers ::__construct
   */
  public function testTestMode() {
    $this->assertEquals(PaytrailBase::MERCHANT_HASH, $this->sut->getMerchantHash());

    $this->sut->setConfiguration([
      'mode' => 'test',
      'merchant_id' => '123',
      'merchant_hash' => '321',
    ] + $this->sut->getConfiguration());
    // Make sure merchant hash and id stays the same when using test mode.
    $this->assertEquals('test', $this->sut->getMode());
    $this->assertEquals(PaytrailBase::MERCHANT_HASH, $this->sut->getMerchantHash());
    $this->assertEquals(PaytrailBase::MERCHANT_ID, $this->sut->getMerchantId());

    // Make sure merchant id does not fallback to the test credentials
    // when using live mode.
    $this->sut->setConfiguration(['mode' => 'live'] + $this->sut->getConfiguration());
    $this->assertEquals('live', $this->sut->getMode());
    $this->assertEquals('321', $this->sut->getMerchantHash());
    $this->assertEquals('123', $this->sut->getMerchantId());
  }

  /**
   * Make sure culture fallback works.
   *
   * @covers ::__construct
   * @covers ::getCulture
   * @covers ::getSetting
   */
  public function testCulture() {
    $this->assertEquals(PaytrailBase::MERCHANT_HASH, $this->sut->getMerchantHash());

    $this->languageManager->expects($this->at(0))
      ->method('getCurrentLanguage')
      ->will($this->returnValue(new Language([
        'name' => 'Finnish',
        'id' => 'fi',
      ])));
    $this->languageManager->expects($this->at(1))
      ->method('getCurrentLanguage')
      ->will($this->returnValue(new Language([
        'name' => 'Danish',
        'id' => 'da',
      ])));

    $this->sut->setConfiguration(['culture' => 'automatic'] + $this->sut->getConfiguration());
    // Make sure auto detection works.
    $this->assertEquals($this->sut->getCulture(), 'fi_FI');
    // Make sure auto fallback works when using an unknown language.
    $this->assertEquals($this->sut->getCulture(), 'en_US');

    // Make sure manually set culture works.
    $this->sut->setConfiguration(['culture' => 'sv_SE'] + $this->sut->getConfiguration());
    $this->assertEquals($this->sut->getCulture(), 'sv_SE');
  }

}
