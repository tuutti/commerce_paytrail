<?php

namespace Drupal\Tests\commerce_paytrail\Unit;

use Drupal\commerce_paytrail\Event\PaymentRepositoryEvent;
use Drupal\commerce_paytrail\Repository\Method;
use Drupal\commerce_paytrail\Repository\MethodRepository;
use Drupal\Tests\UnitTestCase;

/**
 * PaymentRepository unit tests.
 *
 * @group commerce_paytrail
 * @coversDefaultClass \Drupal\commerce_paytrail\Repository\MethodRepository
 */
class PaymentRepositoryTest extends UnitTestCase {

  /**
   * The mocked event dispatcher.
   *
   * @var \PHPUnit_Framework_MockObject_MockObject
   */
  protected $eventDispatcher;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    $this->eventDispatcher = $this->getMock('\Symfony\Component\EventDispatcher\EventDispatcherInterface');
  }

  /**
   * Tests getMethods() method.
   *
   * @covers ::getMethods
   * @covers ::__construct
   * @covers ::getDefaultMethods
   * @covers \Drupal\commerce_paytrail\Repository\Method::__construct
   * @covers \Drupal\commerce_paytrail\Repository\Method::setId
   * @covers \Drupal\commerce_paytrail\Repository\Method::setDisplayLabel
   * @covers \Drupal\commerce_paytrail\Repository\Method::setLabel
   * @covers \Drupal\commerce_paytrail\Repository\Method::getLabel
   * @covers \Drupal\commerce_paytrail\Repository\Method::getDisplayLabel
   * @covers \Drupal\commerce_paytrail\Repository\Method::getId
   * @covers \Drupal\commerce_paytrail\Event\PaymentRepositoryEvent::getPaymentMethods
   * @covers \Drupal\commerce_paytrail\Event\PaymentRepositoryEvent::getPaymentMethod
   * @covers \Drupal\commerce_paytrail\Event\PaymentRepositoryEvent::__construct
   * @covers \Drupal\commerce_paytrail\Event\PaymentRepositoryEvent::setPaymentMethod
   * @covers \Drupal\commerce_paytrail\Event\PaymentRepositoryEvent::setPaymentMethods
   * @covers \Drupal\commerce_paytrail\Event\PaymentRepositoryEvent::unsetPaymentMethod
   */
  public function testGetMethods() {
    $sut = new MethodRepository($this->eventDispatcher);
    $default_methods = $sut->getDefaultMethods();
    $dispatched_event = new PaymentRepositoryEvent($default_methods);

    $this->eventDispatcher->expects($this->any())
      ->method('dispatch')
      ->will($this->returnValue($dispatched_event));

    // Test getters.
    $this->assertNotEmpty($default_methods);
    $this->assertEquals($default_methods, $sut->getMethods());
    $this->assertEquals($default_methods, $dispatched_event->getPaymentMethods());
    $this->assertNull($dispatched_event->getPaymentMethod(999));

    $method = new Method(666, 'Test label', 'Test display label');
    $dispatched_event->setPaymentMethod($method);

    // Test dispatched getter.
    $this->assertEquals($dispatched_event->getPaymentMethod(1), $sut->getMethods()[1]);

    // Test dispatched setters.
    $this->assertEquals($dispatched_event->getPaymentMethod(666), $method);
    $this->assertEquals($sut->getMethods()[666], $method);

    // Test dispatched unset.
    $this->assertTrue(!empty($sut->getMethods()[1]));
    $dispatched_event->unsetPaymentMethod(1);
    $this->assertTrue(empty($sut->getMethods()[1]));

    // Test method.
    $method2 = new Method();
    $method2->setId(666)
      ->setLabel('Test label')
      ->setDisplayLabel('Test display label');

    $this->assertEquals($method, $method2);
    $this->assertTrue($method2->getDisplayLabel() === 'Test display label');
    $this->assertTrue($method2->getId() === 666);
    $this->assertTrue($method2->getLabel() === 'Test label');
  }

}
