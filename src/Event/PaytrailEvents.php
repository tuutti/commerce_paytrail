<?php

namespace Drupal\commerce_paytrail\Event;

/**
 * Class PaytrailEvents.
 *
 * @package Drupal\commerce_paytrail\Events
 */
final class PaytrailEvents {

  /**
   * Event to alter payment method repository values.
   */
  const PAYMENT_REPO_ALTER = 'paytrail.alter_payment_repository';

  /**
   * Event to alter transaction repository values.
   */
  const TRANSACTION_REPO_ALTER = 'paytrail.alter_transaction_repository';

}
