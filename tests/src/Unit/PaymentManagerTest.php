<?php

namespace Drupal\Tests\commerce_paytrail\Unit;

use Drupal\commerce_paytrail\PaymentManager;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Tests\UnitTestCase;

/**
 * PaymentRepository unit tests.
 *
 * @todo Write unit tests for rest of the payment manager once
 * core supports phpunit >= 5.
 *
 * @group commerce_paytrail
 * @coversDefaultClass \Drupal\commerce_paytrail\PaymentManager
 */
class PaymentManagerTest extends UnitTestCase {

  /**
   * The mocked entity type manager.
   *
   * @var \PHPUnit_Framework_MockObject_MockObject|\Drupal\Core\Entity\EntityTypeManagerInterface
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
   * The mocked event dispatcher.
   *
   * @var \PHPUnit_Framework_MockObject_MockObject|\Symfony\Component\EventDispatcher\EventDispatcherInterface
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
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * The module handler.
   *
   * @var \PHPUnit_Framework_MockObject_MockObject|\Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    $this->moduleHandler = $this->getMock('\Drupal\Core\Extension\ModuleHandlerInterface');
    $this->eventDispatcher = $this->getMock('\Symfony\Component\EventDispatcher\EventDispatcherInterface');
    $this->entityTypeManager = $this->getMockBuilder('\Drupal\Core\Entity\EntityTypeManager')
      ->disableOriginalConstructor()
      ->getMock();

    $this->order = $this->getMockBuilder('\Drupal\commerce_order\Entity\Order')
      ->disableOriginalConstructor()
      ->getMock();

    $this->time = $this->getMock('\Drupal\Component\Datetime\TimeInterface');

    $this->urlGenerator = $this->getMock('Drupal\Core\Routing\UrlGeneratorInterface');
    $this->container = new ContainerBuilder();
    $this->container->set('url_generator', $this->urlGenerator);
    \Drupal::setContainer($this->container);

    $this->sut = new PaymentManager($this->entityTypeManager, $this->eventDispatcher, $this->time, $this->moduleHandler);
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

}
