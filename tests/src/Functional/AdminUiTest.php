<?php

declare(strict_types = 1);

namespace Drupal\Tests\commerce_paytrail\Functional;

/**
 * Provides tests for admin ui.
 *
 * @group commerce_paytrail
 */
class AdminUiTest extends PaytrailBrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected function getAdministratorPermissions() : array {
    return array_merge([
      'administer commerce_payment_gateway',
    ], parent::getAdministratorPermissions());
  }

  /**
   * Asserts paytrail payment gateway settings form values.
   *
   * @param string $account
   *   The account.
   * @param string $secret
   *   The secret.
   * @param string $language
   *   The language.
   * @param callable|null $callback
   *   The callback to run with expected values.
   */
  private function assertFormValues(string $account, string $secret, string $language, ?callable $callback = NULL) : void {
    $expected = [
      'configuration[paytrail][account]' => $account,
      'configuration[paytrail][secret]' => $secret,
      'configuration[paytrail][language]' => $language,
    ];

    if ($callback) {
      $callback($expected);
    }
    $this->drupalGet('admin/commerce/config/payment-gateways/manage/paytrail');
    $this->assertSession()->statusCodeEquals(200);

    foreach ($expected as $field => $value) {
      $this->assertSession()->fieldValueEquals($field, $value);
    }
  }

  /**
   * Test payment gateway editing.
   */
  public function testSave() : void {
    // Test default credentials.
    $this->assertFormValues('375917', 'SAIPPUAKAUPPIAS', 'automatic');
    // Test that we can modify values.
    $this->assertFormValues('321', '123', 'EN', fn (array $expected) => $this->submitForm($expected, 'Save'));
  }

}
