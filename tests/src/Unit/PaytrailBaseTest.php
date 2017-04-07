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
   * The plugin config.
   *
   * @var array
   */
  protected $config;

  /**
   * The plugin config.
   *
   * @var array
   */
  protected $plugin;

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

    $this->config = [
      '_entity_id' => 123,
      'payment_type' => 'default',
    ];
    $this->plugin = [
      'payment_type' => 'default',
      'payment_method_types' => [],
      'forms' => [],
      'modes' => [],
    ];
  }

  /**
   * Make sure test mode fallbacks to test credentials.
   *
   * @covers ::getMerchantId
   * @covers ::getMerchantHash
   */
  public function testTestMode() {
    $sut = new PaytrailBase($this->config, '', $this->plugin, $this->entityTypeManager, $this->paymentTypeManager, $this->paymentMethodTypeManager, $this->languageManager, $this->paytrailPaymentManager, $this->logger);
    $this->assertEquals(PaytrailBase::MERCHANT_HASH, $sut->getMerchantHash());

    $sut->setConfiguration([
      'mode' => 'test',
      'merchant_id' => '123',
      'merchant_hash' => '321',
    ] + $sut->getConfiguration());
    // Make sure merchant hash and id stays the same when using test mode.
    $this->assertEquals('test', $sut->getMode());
    $this->assertEquals(PaytrailBase::MERCHANT_HASH, $sut->getMerchantHash());
    $this->assertEquals(PaytrailBase::MERCHANT_ID, $sut->getMerchantId());

    // Make sure merchant id does not fallback to the test credentials
    // when using live mode.
    $sut->setConfiguration(['mode' => 'live'] + $sut->getConfiguration());
    $this->assertEquals('live', $sut->getMode());
    $this->assertEquals('321', $sut->getMerchantHash());
    $this->assertEquals('123', $sut->getMerchantId());
  }

  /**
   * Make sure culture fallback works.
   *
   * @covers ::getCulture
   */
  public function testCulture() {
    $sut = new PaytrailBase($this->config, '', $this->plugin, $this->entityTypeManager, $this->paymentTypeManager, $this->paymentMethodTypeManager, $this->languageManager, $this->paytrailPaymentManager, $this->logger);
    $this->assertEquals(PaytrailBase::MERCHANT_HASH, $sut->getMerchantHash());

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

    $sut->setConfiguration(['culture' => 'automatic'] + $sut->getConfiguration());
    // Make sure auto detection works.
    $this->assertEquals($sut->getCulture(), 'fi_FI');
    // Make sure auto fallback works when using an unknown language.
    $this->assertEquals($sut->getCulture(), 'en_US');

    // Make sure manually set culture works.
    $sut->setConfiguration(['culture' => 'sv_SE'] + $sut->getConfiguration());
    $this->assertEquals($sut->getCulture(), 'sv_SE');
  }

}
