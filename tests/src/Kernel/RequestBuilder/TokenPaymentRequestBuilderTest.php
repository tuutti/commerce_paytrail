<?php

declare(strict_types = 1);

namespace Drupal\Tests\commerce_paytrail\Kernel\RequestBuilder;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_payment\Entity\PaymentInterface;
use Drupal\commerce_paytrail\RequestBuilder\TokenPaymentRequestBuilder;
use Drupal\commerce_paytrail\RequestBuilder\TokenPaymentRequestBuilderInterface;
use Drupal\commerce_price\Price;
use GuzzleHttp\Psr7\Response;
use Paytrail\Payment\ApiException;
use Paytrail\Payment\Model\TokenizationRequestResponse;
use Paytrail\Payment\Model\TokenMITPaymentResponse;
use Paytrail\Payment\Model\TokenPaymentRequest;

/**
 * Tests Token Payment requests.
 *
 * @group commerce_paytrail
 * @coversDefaultClass \Drupal\commerce_paytrail\RequestBuilder\TokenPaymentRequestBuilder
 */
class TokenPaymentRequestBuilderTest extends PaymentRequestBuilderTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->gateway = $this->createGatewayPlugin('paytrail_token', 'paytrail_token');
  }

  /**
   * Gets the SUT.
   *
   * @return \Drupal\commerce_paytrail\RequestBuilder\TokenPaymentRequestBuilder
   *   The system under testing.
   */
  private function getSut() : TokenPaymentRequestBuilder {
    return new TokenPaymentRequestBuilder(
      $this->container->get('uuid'),
      $this->container->get('datetime.time'),
      $this->container->get('event_dispatcher'),
      $this->container->get('http_client'),
      $this->container->get('commerce_price.minor_units_converter'),
      123,
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getRequest(OrderInterface $order) : TokenPaymentRequest {
    return $this->getSut()
      ->createTokenPaymentRequest($order, '123', TokenPaymentRequestBuilderInterface::TOKEN_MIT_CHARGE_EVENT);
  }

  /**
   * @covers ::getCardForToken
   * @covers ::createHeaders
   */
  public function testGetCardForToken() : void {
    $this->setupMockHttpClient([
      new Response(200, [
        // The signature is just copied from ::validateSignature().
        'signature' => '84a0d7a81958c74064a31046365ce344fc38d96e168541684d6ea6596463169b0bd34819e0bab4f56a8a2378a0cb728a55d5a5902c59584ae039482eb449c0a2',
      ],
        json_encode([])),
    ]);

    $response = $this->getSut()
      ->getCardForToken($this->gateway->getPlugin(), '123');

    static::assertCount(1, $this->requestHistory);
    $this->assertRequestHeaders($this->requestHistory[0]['request']);
    static::assertInstanceOf(TokenizationRequestResponse::class, $response);
    // Make sure event dispatcher was triggered for both, request and response.
    static::assertEquals(TokenPaymentRequestBuilderInterface::TOKEN_GET_CARD_EVENT, $this->caughtEvents[0]->event);
    static::assertEquals(TokenPaymentRequestBuilderInterface::TOKEN_GET_CARD_RESPONSE_EVENT, $this->caughtEvents[1]->event);
    static::assertCount(2, $this->caughtEvents);
  }

  /**
   * @covers ::createHeaders
   * @covers ::tokenRevert
   */
  public function testTokenRevert() : void {
    $this->setupMockHttpClient([
      new Response(200, [
        // The signature is just copied from ::validateSignature().
        'signature' => '84a0d7a81958c74064a31046365ce344fc38d96e168541684d6ea6596463169b0bd34819e0bab4f56a8a2378a0cb728a55d5a5902c59584ae039482eb449c0a2',
      ],
        json_encode([])),
    ]);

    $payment = $this->prophesize(PaymentInterface::class);
    $payment->getOrder()->willReturn($this->createOrder());
    $payment->getRemoteId()->willReturn('123');

    $response = $this->getSut()->tokenRevert($payment->reveal());

    static::assertCount(1, $this->requestHistory);
    $this->assertRequestHeaders($this->requestHistory[0]['request']);
    static::assertInstanceOf(TokenMITPaymentResponse::class, $response);
    // Make sure event dispatcher was triggered for response.
    static::assertEquals(TokenPaymentRequestBuilderInterface::TOKEN_REVERT_RESPONSE_EVENT, $this->caughtEvents[0]->event);
    static::assertCount(1, $this->caughtEvents);
  }

  /**
   * @covers ::tokenCommit
   * @covers ::createHeaders
   */
  public function testTokenCommitApiException() : void {
    $this->setupMockHttpClient([
      // Response is expected to be 201. Return non-200 response to return
      // Error model.
      new Response(200, [
        // The signature is just copied from ::validateSignature().
        'signature' => '84a0d7a81958c74064a31046365ce344fc38d96e168541684d6ea6596463169b0bd34819e0bab4f56a8a2378a0cb728a55d5a5902c59584ae039482eb449c0a2',
      ],
        json_encode([])),
    ]);

    $payment = $this->prophesize(PaymentInterface::class);
    $payment->getOrder()->willReturn($this->createOrder());
    $payment->getRemoteId()->willReturn('123');

    $this->expectException(ApiException::class);
    $this->expectExceptionMessage('Failed to capture the payment. No message was given by Paytrail API.');

    $this->getSut()
      ->tokenCommit($payment->reveal(), new Price('123', 'EUR'));
  }

  /**
   * @covers ::tokenCommit
   * @covers ::createHeaders
   * @covers ::populatePaymentRequest
   */
  public function testTokenCommit() : void {
    $this->setupMockHttpClient([
      new Response(201, [
        // The signature is just copied from ::validateSignature().
        'signature' => '84a0d7a81958c74064a31046365ce344fc38d96e168541684d6ea6596463169b0bd34819e0bab4f56a8a2378a0cb728a55d5a5902c59584ae039482eb449c0a2',
      ],
        json_encode([])),
    ]);

    $payment = $this->prophesize(PaymentInterface::class);
    $payment->getOrder()->willReturn($this->createOrder());
    $payment->getRemoteId()->willReturn('123');

    $response = $this->getSut()
      ->tokenCommit($payment->reveal(), new Price('123', 'EUR'));

    static::assertCount(1, $this->requestHistory);
    $this->assertRequestHeaders($this->requestHistory[0]['request']);
    static::assertInstanceOf(TokenMITPaymentResponse::class, $response);
    // Make sure event dispatcher was triggered for both, request and response.
    static::assertEquals(TokenPaymentRequestBuilderInterface::TOKEN_COMMIT_EVENT, $this->caughtEvents[0]->event);
    static::assertEquals(TokenPaymentRequestBuilderInterface::TOKEN_COMMIT_RESPONSE_EVENT, $this->caughtEvents[1]->event);
    static::assertCount(2, $this->caughtEvents);
  }

  /**
   * @covers ::tokenMitAuthorize
   * @covers ::createHeaders
   * @covers ::createTokenPaymentRequest
   * @covers ::populatePaymentRequest
   */
  public function testTokenMitAuthorize() : void {
    $this->setupMockHttpClient([
      new Response(201, [
        // The signature is just copied from ::validateSignature().
        'signature' => '84a0d7a81958c74064a31046365ce344fc38d96e168541684d6ea6596463169b0bd34819e0bab4f56a8a2378a0cb728a55d5a5902c59584ae039482eb449c0a2',
      ],
        json_encode([])),
    ]);

    $response = $this->getSut()
      ->tokenMitAuthorize($this->createOrder(), '123');

    static::assertCount(1, $this->requestHistory);
    $this->assertRequestHeaders($this->requestHistory[0]['request']);
    static::assertInstanceOf(TokenMITPaymentResponse::class, $response);
    // Make sure event dispatcher was triggered for both, request and response.
    static::assertEquals(TokenPaymentRequestBuilderInterface::TOKEN_MIT_AUTHORIZE_EVENT, $this->caughtEvents[0]->event);
    static::assertEquals(TokenPaymentRequestBuilderInterface::TOKEN_MIT_AUTHORIZE_RESPONSE_EVENT, $this->caughtEvents[1]->event);
    static::assertCount(2, $this->caughtEvents);
  }

  /**
   * @covers ::tokenMitCharge
   * @covers ::createHeaders
   * @covers ::createTokenPaymentRequest
   * @covers ::populatePaymentRequest
   */
  public function testTokenMitCharge() : void {
    $this->setupMockHttpClient([
      new Response(201, [
        // The signature is just copied from ::validateSignature().
        'signature' => '84a0d7a81958c74064a31046365ce344fc38d96e168541684d6ea6596463169b0bd34819e0bab4f56a8a2378a0cb728a55d5a5902c59584ae039482eb449c0a2',
      ],
        json_encode([])),
    ]);

    $response = $this->getSut()
      ->tokenMitCharge($this->createOrder(), '123');

    static::assertCount(1, $this->requestHistory);
    $this->assertRequestHeaders($this->requestHistory[0]['request']);
    static::assertInstanceOf(TokenMITPaymentResponse::class, $response);
    // Make sure event dispatcher was triggered for both, request and response.
    static::assertEquals(TokenPaymentRequestBuilderInterface::TOKEN_MIT_CHARGE_EVENT, $this->caughtEvents[0]->event);
    static::assertEquals(TokenPaymentRequestBuilderInterface::TOKEN_MIT_CHARGE_RESPONSE_EVENT, $this->caughtEvents[1]->event);
    static::assertCount(2, $this->caughtEvents);
  }

}
