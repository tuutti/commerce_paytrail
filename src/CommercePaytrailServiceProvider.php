<?php

declare(strict_types = 1);

namespace Drupal\commerce_paytrail;

use Drupal\commerce_paytrail\Commerce\Shipping\ShippingEventSubscriber;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Registers services for non-required modules.
 */
class CommercePaytrailServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container) :void {
    // We cannot use the module handler as the container is not yet compiled.
    // @see \Drupal\Core\DrupalKernel::compileContainer()
    $modules = $container->getParameter('container.modules');

    if (isset($modules['commerce_shipping'])) {
      $container->register('commerce_paytrail.shipping_subscriber', ShippingEventSubscriber::class)
        ->addTag('event_subscriber')
        ->addArgument(new Reference('commerce_price.minor_units_converter'));
    }
    $container->removeDefinition('commerce_paytrail.request_builder_subscriber');
  }

}
