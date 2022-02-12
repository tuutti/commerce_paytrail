<?php

declare(strict_types=1);

namespace Drupal\Tests\commerce_paytrail\Traits;

use Drupal\commerce_paytrail\Event\ModelEvent;
use Drupal\Core\DependencyInjection\ContainerBuilder;

/**
 * Provides a trait to test event subscribers.
 */
trait EventSubscriberTestTrait {

  /**
   * Track caught events in a property for testing.
   *
   * @var array
   */
  protected ?array $caughtEvents = [];

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() : array {
    return [
      self::getEventClassName() => ['catchEvents'],
    ];
  }

  /**
   * The expected event class name to catch.
   *
   * @return string
   *   The event class name.
   */
  abstract public static function getEventClassName() : string;

  /**
   * Catch events.
   *
   * @param \Drupal\commerce_paytrail\Event\ModelEvent $event
   *   The event.
   */
  public function catchEvents(ModelEvent $event): void {
    $this->caughtEvents[] = $event;
  }

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container): void {
    parent::register($container);
    $container
      ->register('testing.test_subscriber', self::class)
      ->addTag('event_subscriber');
    $container->set('testing.test_subscriber', $this);
  }

  /**
   * Asserts caught events.
   *
   * @param int $expectedCount
   *   The expected amount of caught events.
   * @param callable $callback
   *   The callback.
   */
  public function assertCaughtEvents(int $expectedCount, callable $callback) : void {
    static::assertCount(0, $this->caughtEvents);

    $callback();

    static::assertCount($expectedCount, $this->caughtEvents);
  }

}
