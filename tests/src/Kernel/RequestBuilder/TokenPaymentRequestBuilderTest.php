<?php

declare(strict_types = 1);

namespace Drupal\Tests\commerce_paytrail\Kernel\RequestBuilder;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_payment\Entity\PaymentInterface;
use Drupal\commerce_paytrail\RequestBuilder\TokenRequestBuilder;
use Drupal\commerce_paytrail\RequestBuilder\TokenRequestBuilderInterface;
use Drupal\commerce_price\Price;
use GuzzleHttp\Psr7\Response;
use Paytrail\SDK\Request\MitPaymentRequest;
use Paytrail\SDK\Response\GetTokenResponse;
use Paytrail\SDK\Response\MitPaymentResponse;
use Paytrail\SDK\Response\RevertPaymentAuthHoldResponse;

/**
 * Tests Token Payment requests.
 *
 * @group commerce_paytrail
 * @coversDefaultClass \Drupal\commerce_paytrail\RequestBuilder\TokenRequestBuilder
 */
class TokenPaymentRequestBuilderTest extends PaymentRequestBuilderTestBase {

  /**
   * Gets the SUT.
   *
   * @return \Drupal\commerce_paytrail\RequestBuilder\TokenRequestBuilder
   *   The system under testing.
   */
  private function getSut() : TokenRequestBuilder {
    return new TokenRequestBuilder(
      $this->container->get('uuid'),
      $this->container->get('datetime.time'),
      $this->container->get('event_dispatcher'),
      $this->container->get('commerce_price.minor_units_converter'),
      123,
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getRequest(OrderInterface $order) : MitPaymentRequest {
    return $this->getSut()
      ->createTokenPaymentRequest($order, '123', TokenRequestBuilderInterface::TOKEN_MIT_CHARGE_EVENT);
  }

  /**
   * @covers ::createAddCardFormForOrder
   */
  public function testCreateAddCardFormForOrder() : void {
    $response = $this->getSut()
      ->createAddCardFormForOrder($this->createOrder($this->createGatewayPlugin('paytrail_token', 'paytrail_token')));

    static::assertArrayHasKey('uri', $response);
    static::assertArrayHasKey('data', $response);
    static::assertEquals(TokenRequestBuilderInterface::TOKEN_ADD_CARD_FORM_EVENT, $this->caughtEvents[0]->event);
    static::assertCount(1, $this->caughtEvents);
  }

  /**
   * @covers ::getCardForToken
   * @covers \Drupal\commerce_paytrail\Plugin\Commerce\PaymentGateway\PaytrailBase::getClient
   * @covers \Drupal\commerce_paytrail\Http\PaytrailClientFactory::create
   * @covers \Drupal\commerce_paytrail\EventSubscriber\PaymentRequestSubscriberBase::isValid
   */
  public function testGetCardForToken() : void {
    $this->setupMockHttpClient([
      new Response(200, [
        'signature' => 'a7082f6aa570a6e8d194a3bdc3f74863037be637112a4f3a23410df6156d5c1b',
      ],
        json_encode([
          'token' => '123',
          'card' => [
            'type' => 'visa',
            'bin' => '1234',
            'partial_pan' => '1234',
            'expire_year' => '2023',
            'expire_month' => '12',
            'cvc_required' => FALSE,
            'funding' => '',
            'category' => '',
            'country_code' => '',
            'pan_fingerprint' => '',
            'card_fingerprint' => '',
          ],
          'customer' => [
            'network_address'  => '',
            'country_code' => '',
          ],
        ])),
    ]);

    $gateway = $this->createGatewayPlugin('paytrail_token', 'paytrail_token');

    $response = $this->getSut()
      ->getCardForToken($gateway->getPlugin(), '123');

    static::assertCount(1, $this->requestHistory);
    $this->assertRequestHeaders($this->requestHistory[0]['request']);
    static::assertInstanceOf(GetTokenResponse::class, $response);
    // Make sure event dispatcher was triggered for both, request and response.
    static::assertEquals(TokenRequestBuilderInterface::TOKEN_GET_CARD_EVENT, $this->caughtEvents[0]->event);
    static::assertEquals(TokenRequestBuilderInterface::TOKEN_GET_CARD_RESPONSE_EVENT, $this->caughtEvents[1]->event);
    static::assertCount(2, $this->caughtEvents);
  }

  /**
   * @covers ::tokenRevert
   * @covers \Drupal\commerce_paytrail\EventSubscriber\PaymentRequestSubscriberBase::isValid
   */
  public function testTokenRevert() : void {
    $this->setupMockHttpClient([
      new Response(200, [
        'signature' => 'd8e92eed91585b7a1ad80761064db7d7e9b960a69cd15f6c2581c5d3142ceaa2',
      ],
        json_encode([])),
    ]);

    $payment = $this->prophesize(PaymentInterface::class);
    $payment->getOrder()
      ->willReturn($this->createOrder($this->createGatewayPlugin('paytrail_token', 'paytrail_token')));
    $payment->getRemoteId()->willReturn('123');

    $response = $this->getSut()->tokenRevert($payment->reveal());

    static::assertCount(1, $this->requestHistory);
    $this->assertRequestHeaders($this->requestHistory[0]['request']);
    static::assertInstanceOf(RevertPaymentAuthHoldResponse::class, $response);
    // Make sure event dispatcher was triggered for response.
    static::assertEquals(TokenRequestBuilderInterface::TOKEN_REVERT_RESPONSE_EVENT, $this->caughtEvents[0]->event);
    static::assertCount(1, $this->caughtEvents);
  }

  /**
   * @covers ::tokenCommit
   * @covers ::populatePaymentRequest
   * @covers ::createMitPaymentAction
   * @covers \Drupal\commerce_paytrail\EventSubscriber\PaymentRequestSubscriberBase::isValid
   */
  public function testTokenCommit() : void {
    $this->setupMockHttpClient([
      new Response(201, [
        'signature' => 'd8e92eed91585b7a1ad80761064db7d7e9b960a69cd15f6c2581c5d3142ceaa2',
      ],
        json_encode([])),
    ]);

    $payment = $this->prophesize(PaymentInterface::class);
    $payment->getOrder()
      ->willReturn($this->createOrder($this->createGatewayPlugin('paytrail_token', 'paytrail_token')));
    $payment->getRemoteId()->willReturn('123');

    $response = $this->getSut()
      ->tokenCommit($payment->reveal(), new Price('123', 'EUR'));

    static::assertCount(1, $this->requestHistory);
    $this->assertRequestHeaders($this->requestHistory[0]['request']);
    static::assertInstanceOf(MitPaymentResponse::class, $response);
    // Make sure event dispatcher was triggered for both, request and response.
    static::assertEquals(TokenRequestBuilderInterface::TOKEN_COMMIT_EVENT, $this->caughtEvents[0]->event);
    static::assertEquals(TokenRequestBuilderInterface::TOKEN_COMMIT_RESPONSE_EVENT, $this->caughtEvents[1]->event);
    static::assertCount(2, $this->caughtEvents);
  }

  /**
   * @covers ::tokenMitAuthorize
   * @covers ::createTokenPaymentRequest
   * @covers ::populatePaymentRequest
   * @covers ::createMitPaymentAction
   * @covers \Drupal\commerce_paytrail\EventSubscriber\PaymentRequestSubscriberBase::isValid
   */
  public function testTokenMitAuthorize() : void {
    $this->setupMockHttpClient([
      new Response(201, [
        'signature' => 'd8e92eed91585b7a1ad80761064db7d7e9b960a69cd15f6c2581c5d3142ceaa2',
      ],
        json_encode([])),
    ]);

    $response = $this->getSut()
      ->tokenMitAuthorize($this->createOrder($this->createGatewayPlugin('paytrail_token', 'paytrail_token')), '123');

    static::assertCount(1, $this->requestHistory);
    $this->assertRequestHeaders($this->requestHistory[0]['request']);
    static::assertInstanceOf(MitPaymentResponse::class, $response);
    // Make sure event dispatcher was triggered for both, request and response.
    static::assertEquals(TokenRequestBuilderInterface::TOKEN_MIT_AUTHORIZE_EVENT, $this->caughtEvents[0]->event);
    static::assertEquals(TokenRequestBuilderInterface::TOKEN_MIT_AUTHORIZE_RESPONSE_EVENT, $this->caughtEvents[1]->event);
    static::assertCount(2, $this->caughtEvents);
  }

  /**
   * @covers ::tokenMitCharge
   * @covers ::createTokenPaymentRequest
   * @covers ::populatePaymentRequest
   * @covers ::createMitPaymentAction
   * @covers \Drupal\commerce_paytrail\EventSubscriber\PaymentRequestSubscriberBase::isValid
   */
  public function testTokenMitCharge() : void {
    $this->setupMockHttpClient([
      new Response(201, [
        'signature' => 'd8e92eed91585b7a1ad80761064db7d7e9b960a69cd15f6c2581c5d3142ceaa2',
      ],
        json_encode([])),
    ]);

    $response = $this->getSut()
      ->tokenMitCharge($this->createOrder($this->createGatewayPlugin('paytrail_token', 'paytrail_token')), '123');

    static::assertCount(1, $this->requestHistory);
    $this->assertRequestHeaders($this->requestHistory[0]['request']);
    static::assertInstanceOf(MitPaymentResponse::class, $response);
    // Make sure event dispatcher was triggered for both, request and response.
    static::assertEquals(TokenRequestBuilderInterface::TOKEN_MIT_CHARGE_EVENT, $this->caughtEvents[0]->event);
    static::assertEquals(TokenRequestBuilderInterface::TOKEN_MIT_CHARGE_RESPONSE_EVENT, $this->caughtEvents[1]->event);
    static::assertCount(2, $this->caughtEvents);
  }

}
