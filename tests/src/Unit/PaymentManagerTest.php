<?php

namespace Drupal\Tests\commerce_paytrail\Unit;

use Drupal\commerce_paytrail\PaymentManager;
use Drupal\commerce_paytrail\Plugin\Commerce\PaymentGateway\PaytrailBase;
use Drupal\commerce_paytrail\Repository\Method;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Tests\UnitTestCase;

/**
 * PaymentRepository unit tests.
 *
 * @group commerce_paytrail
 * @coversDefaultClass \Drupal\commerce_paytrail\PaymentManager
 */
class PaymentManagerTest extends UnitTestCase {

  /**
   * The mocked entity type manager.
   *
   * @var \PHPUnit_Framework_MockObject_MockObject
   */
  protected $entityTypeManager;

  /**
   * The container.
   *
   * @var \Symfony\Component\DependencyInjection\ContainerInterface
   */
  protected $container;

  /**
   * The URL generator.
   *
   * @var \Drupal\Core\Routing\UrlGeneratorInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $urlGenerator;

  /**
   * The mocked method repository.
   *
   * @var \PHPUnit_Framework_MockObject_MockObject
   */
  protected $methodRepository;

  /**
   * The mocked event dispatcher.
   *
   * @var \PHPUnit_Framework_MockObject_MockObject
   */
  protected $eventDispatcher;

  /**
   * The mocked order.
   *
   * @var \PHPUnit_Framework_MockObject_MockObject|\Drupal\commerce_order\Entity\Order
   */
  protected $order;

  /**
   * The payment manager.
   *
   * @var \Drupal\commerce_paytrail\PaymentManager
   */
  protected $sut;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    $this->eventDispatcher = $this->getMock('\Symfony\Component\EventDispatcher\EventDispatcherInterface');
    $this->entityTypeManager = $this->getMockBuilder('\Drupal\Core\Entity\EntityTypeManager')
      ->disableOriginalConstructor()
      ->getMock();

    $this->methodRepository = $this->getMockBuilder('\Drupal\commerce_paytrail\Repository\MethodRepository')
      ->disableOriginalConstructor()
      ->getMock();

    $this->order = $this->getMockBuilder('\Drupal\commerce_order\Entity\Order')
      ->disableOriginalConstructor()
      ->getMock();

    $this->urlGenerator = $this->getMock('Drupal\Core\Routing\UrlGeneratorInterface');
    $this->container = new ContainerBuilder();
    $this->container->set('url_generator', $this->urlGenerator);
    \Drupal::setContainer($this->container);

    $this->sut = new PaymentManager($this->entityTypeManager, $this->eventDispatcher, $this->methodRepository);
  }

  /**
   * Tests getPaymentMethods() method.
   *
   * @covers ::__construct
   * @covers ::getPaymentMethods
   */
  public function testGetPaymentMethods() {
    $data = [
      1 => new Method(1, 'Label 1', 'Label 1'),
      2 => new Method(2, 'Label 2', 'Label 2'),
      3 => new Method(3, 'Label 3', 'Label 3'),
    ];
    $this->methodRepository->expects($this->any())
      ->method('getMethods')
      ->will($this->returnValue($data));

    $response = $this->sut->getPaymentMethods();
    $this->assertEquals($response, $data);

    // Test enabled methods.
    $response = $this->sut->getPaymentMethods([2, 3]);
    $this->assertEquals($response, [
      2 => new Method(2, 'Label 2', 'Label 2'),
      3 => new Method(3, 'Label 3', 'Label 3'),
    ]);
  }

  /**
   * Tests getReturnUrl() method.
   *
   * @covers ::getReturnUrl
   */
  public function testGetReturnUrl() {
    $this->urlGenerator->expects($this->at(0))
      ->method('generateFromRoute')
      ->will($this->returnValue('http://localhost/pending'));

    $this->urlGenerator->expects($this->at(1))
      ->method('generateFromRoute')
      ->will($this->returnValue('http://localhost/return'));

    foreach (['pending', 'return'] as $i => $type) {
      $response = $this->sut->getReturnUrl($this->order, $type);
      $this->assertEquals('http://localhost/' . $type, $response);
    }
  }

  /**
   * Tests getRedirectKey() method.
   *
   * @covers ::getRedirectKey
   * @covers ::getTime
   */
  public function testGetRedirectKey() {
    $response = $this->sut->getRedirectKey($this->order);

    $this->order->expects($this->at(0))
      ->method('getData')
      ->will($this->returnValue($response));

    // Make sure redirect key returns the same key when
    // saved to an order.
    $response2 = $this->sut->getRedirectKey($this->order);
    $this->assertEquals($response, $response2);
  }

  /**
   * Tests generateReturnChecksum() method.
   *
   * @covers ::generateAuthCode
   * @dataProvider generateReturnChecksumProvider
   */
  public function testGenerateReturnChecksum($hash, $values, $expected) {
    $return = $this->sut->generateReturnChecksum($hash, $values);
    $this->assertEquals($return, $expected);
  }

  /**
   * Data provider for testGenerateReturnChecksum().
   */
  public function generateReturnChecksumProvider() {
    return [
      ['testHash', [1, 2, 3, 4], strtoupper(md5('1|2|3|4|testHash'))],
      ['hAsH', ['dsa' => '123', 'dd' => '22'], strtoupper(md5('123|22|hAsH'))],
    ];
  }

  /**
   * Tests generateAuthCode() method.
   *
   * @covers ::generateAuthCode
   * @dataProvider generateAuthCodeProvider
   */
  public function testGenerateAuthCode($hash, $values, $expected) {
    $return = $this->sut->generateAuthCode($hash, $values);
    $this->assertEquals($return, $expected);
  }

  /**
   * Data provider for testGenerateAuthCode().
   */
  public function generateAuthCodeProvider() {
    return [
      ['testHash',
        [
          'test' => 1,
          'test2' => '233',
          'value' => 'jo0',
        ],
        strtoupper(md5('testHash|1|233|jo0')),
      ],
      ['MhASh', [1, 2, 3, 4, 5], strtoupper(md5('MhASh|1|2|3|4|5'))],
    ];
  }

}
