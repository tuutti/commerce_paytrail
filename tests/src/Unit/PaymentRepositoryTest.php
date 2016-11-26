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
   * Tests MethodRepository and all its dependencies.
   *
   * @covers \Drupal\commerce_paytrail\Repository\MethodRepository::
   * @covers \Drupal\commerce_paytrail\Repository\Method::
   * @covers \Drupal\commerce_paytrail\Event\PaymentRepositoryEvent::
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

    // Test dispatched getter.
    $this->assertEquals($dispatched_event->getPaymentMethod(1), $sut->getMethods()[1]);

    // Test dispatched setters.
    $method = new Method(666, 'Test label', 'Test display label');
    $dispatched_event->setPaymentMethod($method);

    $this->assertEquals($dispatched_event->getPaymentMethod(666), $method);
    $this->assertEquals($sut->getMethods()[666], $method);

    // Test dispatched unset.
    $this->assertTrue(!empty($sut->getMethods()[1]));
    $dispatched_event->unsetPaymentMethod(1);
    $this->assertTrue(empty($sut->getMethods()[1]));

    // Test Method.
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
