<?php

declare(strict_types=1);

namespace Drupal\Tests\commerce_paytrail\Kernel\Plugin\Commerce\PaymentGateway;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_payment\Entity\PaymentGatewayInterface;
use Drupal\commerce_payment\Exception\PaymentGatewayException;
use Drupal\commerce_paytrail\RequestBuilder\PaymentRequestBuilderInterface;
use Drupal\commerce_paytrail\RequestBuilder\TokenRequestBuilderInterface;
use Drupal\commerce_price\Price;
use Drupal\Tests\commerce_paytrail\Kernel\RequestBuilderKernelTestBase;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Paytrail\SDK\Model\Token\Card;
use Paytrail\SDK\Response\GetTokenResponse;
use Paytrail\SDK\Response\MitPaymentResponse;
use Paytrail\SDK\Response\PaymentStatusResponse;
use Paytrail\SDK\Response\RevertPaymentAuthHoldResponse;
use Prophecy\Argument;

/**
 * Paytrail gateway plugin tests.
 *
 * @group commerce_paytrail
 * @coversDefaultClass \Drupal\commerce_paytrail\Plugin\Commerce\PaymentGateway\PaytrailToken
 */
class TokenPaymentTest extends RequestBuilderKernelTestBase {

  /**
   * Creates a new payment method and payment for given order.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order.
   * @param \Drupal\commerce_payment\Entity\PaymentGatewayInterface $gateway
   *   The gateway.
   *
   * @return array
   *   The data.
   */
  private function createMockPayment(OrderInterface $order, PaymentGatewayInterface $gateway) : array {
    /** @var \Drupal\commerce_payment\PaymentMethodStorageInterface $paymentMethodStorage */
    $paymentMethodStorage = $this->container->get('entity_type.manager')->getStorage('commerce_payment_method');
    /** @var \Drupal\commerce_payment\Entity\PaymentMethodInterface $paymentMethod */
    $paymentMethod = $paymentMethodStorage->create([
      'type' => 'paytrail_token',
      'payment_gateway' => 'paytrail_token',
    ]);
    $paymentMethod->setRemoteId('123')
      ->save();

    /** @var \Drupal\commerce_payment\PaymentStorageInterface $paymentStorage */
    $paymentStorage = $this->container->get('entity_type.manager')->getStorage('commerce_payment');
    /** @var \Drupal\commerce_payment\Entity\PaymentInterface $payment */
    $payment = $paymentStorage->create([
      'order_id' => $order->id(),
      'type' => 'payment_default',
      'payment_gateway' => $gateway->id(),
      'payment_method' => $paymentMethod->id(),
    ]);
    $payment->setAmount($order->getTotalPrice());
    $payment->save();

    return ['payment' => $payment, 'payment_method' => $paymentMethod];
  }

  /**
   * Constructs a new mock request exception.
   *
   * @return \GuzzleHttp\Exception\RequestException
   *   The exception.
   */
  private function createRequestException() : RequestException {
    return new RequestException('', $this->prophesize(Request::class)->reveal(), new Response(403));
  }

  /**
   * @covers ::onNotify
   * @covers ::onNotifySuccess
   */
  public function testOrderNotFoundException() : void {
    $gateway = $this->createGatewayPlugin('paytrail_token', 'paytrail_token');
    $order = $this->createOrder($gateway);
    $sut = $gateway->getPlugin();

    foreach ([NULL, 55] as $orderId) {
      $request = $this->createRequest($sut, [
        'commerce_order' => $orderId,
      ]);
      $response = $sut->onNotify($request);
      static::assertEquals(403, $response->getStatusCode());
      static::assertEquals('Order not found.', $response->getContent());
    }
  }

  /**
   * @covers ::onReturn
   * @covers ::onNotify
   * @covers ::validateResponse
   * @covers ::onNotifySuccess
   */
  public function testSignatureValidationFailed() : void {
    $gateway = $this->createGatewayPlugin('paytrail_token', 'paytrail_token');
    $order = $this->createOrder($gateway);
    $order->setData(TokenRequestBuilderInterface::TOKEN_STAMP_KEY, '123')
      ->save();

    /** @var \Drupal\commerce_paytrail\Plugin\Commerce\PaymentGateway\PaytrailToken $sut */
    $sut = $gateway->getPlugin();
    $request = $this->createRequest($sut, [
      'commerce_order' => $order->id(),
      'commerce_paytrail_stamp' => $order->getData(TokenRequestBuilderInterface::TOKEN_STAMP_KEY),
      'checkout-tokenization-id' => '123',
    ]);
    $request->query->set('signature', '123');

    $response = $sut
      ->onNotify($request);
    static::assertEquals(403, $response->getStatusCode());
    static::assertEquals('Signature does not match.', $response->getContent());

    $this->expectException(PaymentGatewayException::class);
    $this->expectExceptionMessage('Signature does not match.');
    $sut->onReturn($order, $request);
  }

  /**
   * @covers ::onReturn
   * @covers ::onNotify
   * @covers ::onNotifySuccess
   * @covers ::validateResponse
   */
  public function testTokenizationIdNotSetException() : void {
    $gateway = $this->createGatewayPlugin('paytrail_token', 'paytrail_token');
    $order = $this->createOrder($gateway);
    /** @var \Drupal\commerce_paytrail\Plugin\Commerce\PaymentGateway\PaytrailToken $sut */
    $sut = $gateway->getPlugin();
    $request = $this->createRequest($sut, [
      'commerce_order' => $order->id(),
      'checkout-tokenization-id' => NULL,
    ]);

    $response = $sut
      ->onNotify($request);
    static::assertEquals(403, $response->getStatusCode());
    static::assertEquals('Tokenization ID not set.', $response->getContent());

    $this->expectException(PaymentGatewayException::class);
    $this->expectExceptionMessage('Tokenization ID not set.');
    $sut->onReturn($order, $request);
  }

  /**
   * The data provider for stamp mismatch exception test.
   *
   * @return array
   *   The data.
   */
  public function stampMismatchExceptionData() : array {
    return [
      [
        NULL,
        NULL,
      ],
      [
        '123',
        NULL,
      ],
      [
        NULL,
        '123',
      ],
      [
        '123',
        '321',
      ],
    ];
  }

  /**
   * @covers ::onReturn
   * @covers ::onNotify
   * @covers ::onNotifySuccess
   * @dataProvider stampMismatchExceptionData
   */
  public function testStampMismatchException(?string $stamp, ?string $orderStamp) : void {
    $gateway = $this->createGatewayPlugin('paytrail_token', 'paytrail_token');
    $order = $this->createOrder($gateway);
    /** @var \Drupal\commerce_paytrail\Plugin\Commerce\PaymentGateway\PaytrailToken $sut */
    $sut = $gateway->getPlugin();
    $request = $this->createRequest($sut, [
      'commerce_order' => $order->id(),
      'checkout-tokenization-id' => '123',
      'commerce_paytrail_stamp' => $stamp,
    ]);

    $order->setData(TokenRequestBuilderInterface::TOKEN_STAMP_KEY, $orderStamp)
      ->save();

    $response = $sut
      ->onNotify($request);
    static::assertEquals(403, $response->getStatusCode());
    static::assertEquals('Order stamp does not match.', $response->getContent());

    $this->expectException(PaymentGatewayException::class);
    $this->expectExceptionMessage('Order stamp does not match.');
    $sut->onReturn($order, $request);
  }

  /**
   * Creates a successful handle payment call.
   *
   * @return array
   *   The objects to validate.
   */
  private function createHandlePayment() : array {
    $paymentRequestBuilder = $this->prophesize(PaymentRequestBuilderInterface::class);
    $paymentRequestBuilder
      ->get(Argument::any(), Argument::any())
      ->shouldBeCalled()
      ->willReturn(
        (new PaymentStatusResponse())
          ->setStatus('ok')
          ->setTransactionId('123')
      );
    $tokenResponse = new GetTokenResponse();
    $tokenResponse->setToken('123')
      ->setCard((new Card())
        ->setType('Visa')
        ->setPartialPan('123')
        ->setExpireMonth('12')
        ->setExpireYear('2023')
      );
    $tokenBuilder = $this->prophesize(TokenRequestBuilderInterface::class);
    $tokenBuilder->getCardForToken(Argument::any(), Argument::any())
      ->willReturn($tokenResponse);

    $mitPaymentResponse = new MitPaymentResponse();
    $mitPaymentResponse->setTransactionId('123');
    $tokenBuilder->tokenMitAuthorize(Argument::any(), Argument::any())
      ->willReturn($mitPaymentResponse);
    $tokenBuilder->tokenCommit(Argument::any(), Argument::any())
      ->willReturn($mitPaymentResponse);

    $gateway = $this->mockPaymentGateway(
      paymentRequestBuilder: $paymentRequestBuilder->reveal(),
      tokenPaymentRequestBuilder: $tokenBuilder->reveal(),
      plugin: 'paytrail_token',
    );
    $order = $this->createOrder($gateway);
    /** @var \Drupal\commerce_paytrail\Plugin\Commerce\PaymentGateway\PaytrailToken $sut */
    $sut = $gateway->getPlugin();
    $request = $this->createRequest($sut, [
      'commerce_order' => $order->id(),
      'checkout-tokenization-id' => '123',
      'commerce_paytrail_stamp' => '123',
    ]);

    $order->setData(TokenRequestBuilderInterface::TOKEN_STAMP_KEY, '123')
      ->save();

    return [
      'sut' => $sut,
      'order' => $order,
      'request' => $request,
    ];
  }

  /**
   * @covers ::onReturn
   * @covers ::validateResponse
   * @covers ::handlePayment
   * @covers ::createPaymentMethod
   * @covers ::createPayment
   * @covers ::capturePayment
   */
  public function testHandlePaymentOnReturn() : void {
    [
      'order' => $order,
      'sut' => $sut,
      'request' => $request,
    ] = $this->createHandlePayment();

    $sut->onReturn($order, $request);
    $payment = $this->loadPayment('123');

    static::assertEquals('completed', $payment->getState()->getId());
    static::assertEquals('ok', $payment->getRemoteState());
    static::assertEquals('22', $payment->getAmount()->getNumber());
  }

  /**
   * @covers ::handlePayment
   * @covers ::createPaymentMethod
   * @covers ::createPayment
   * @covers ::capturePayment
   * @covers ::validateResponse
   * @covers ::onNotifySuccess
   * @covers ::onNotify
   */
  public function testHandleOnNotifyPayment() : void {
    [
      'sut' => $sut,
      'request' => $request,
    ] = $this->createHandlePayment();

    $response = $sut->onNotify($request);
    static::assertEquals(200, $response->getStatusCode());

    $payment = $this->loadPayment('123');

    static::assertEquals('completed', $payment->getState()->getId());
    static::assertEquals('ok', $payment->getRemoteState());
    static::assertEquals('22', $payment->getAmount()->getNumber());
  }

  /**
   * @covers ::createPayment
   */
  public function testCreatePaymentException() : void {
    $tokenBuilder = $this->prophesize(TokenRequestBuilderInterface::class);
    $tokenBuilder->tokenMitAuthorize(Argument::any(), Argument::any())
      ->willThrow($this->createRequestException());

    $gateway = $this->mockPaymentGateway(
      tokenPaymentRequestBuilder: $tokenBuilder->reveal(),
      plugin: 'paytrail_token',
    );
    $order = $this->createOrder($gateway);

    /** @var \Drupal\commerce_payment\Entity\PaymentInterface $payment  */
    ['payment' => $payment] = $this->createMockPayment($order, $gateway);

    $this->expectException(PaymentGatewayException::class);
    /** @var \Drupal\commerce_paytrail\Plugin\Commerce\PaymentGateway\PaytrailToken $sut */
    $sut = $gateway->getPlugin();
    $sut->createPayment($payment);
  }

  /**
   * @covers ::deletePaymentMethod
   */
  public function testDeletePaymentMethod() : void {
    $gateway = $this->createGatewayPlugin('paytrail_token', 'paytrail_token');

    /** @var \Drupal\commerce_payment\PaymentMethodStorageInterface $paymentMethodStorage */
    $paymentMethodStorage = $this->container->get('entity_type.manager')->getStorage('commerce_payment_method');
    /** @var \Drupal\commerce_payment\Entity\PaymentMethodInterface $paymentMethod */
    $paymentMethod = $paymentMethodStorage->create([
      'type' => 'paytrail_token',
      'payment_gateway' => 'paytrail_token',
    ]);
    $paymentMethod->setRemoteId('123')
      ->save();

    $paymentMethodId = $paymentMethod->id();
    static::assertNotNull($paymentMethodId);
    $gateway->getPlugin()->deletePaymentMethod($paymentMethod);

    static::assertNull($paymentMethodStorage->load($paymentMethodId));
  }

  /**
   * @covers ::voidPayment
   */
  public function testVoidPaymentException() : void {
    $tokenBuilder = $this->prophesize(TokenRequestBuilderInterface::class);
    $tokenBuilder->tokenRevert(Argument::any())
      ->willThrow($this->createRequestException());

    $gateway = $this->mockPaymentGateway(
      tokenPaymentRequestBuilder: $tokenBuilder->reveal(),
      plugin: 'paytrail_token',
    );
    $order = $this->createOrder($gateway);

    /** @var \Drupal\commerce_payment\Entity\PaymentInterface $payment  */
    ['payment' => $payment] = $this->createMockPayment($order, $gateway);
    $payment->setState('authorization')->save();

    $this->expectException(PaymentGatewayException::class);
    $gateway->getPlugin()->voidPayment($payment);
  }

  /**
   * @covers ::voidPayment
   */
  public function testVoidPayment() : void {
    $tokenBuilder = $this->prophesize(TokenRequestBuilderInterface::class);
    $tokenBuilder->tokenRevert(Argument::any())
      ->willReturn(
        (new RevertPaymentAuthHoldResponse())
          ->setTransactionId('123')
      );

    $gateway = $this->mockPaymentGateway(
      tokenPaymentRequestBuilder: $tokenBuilder->reveal(),
      plugin: 'paytrail_token',
    );
    $order = $this->createOrder($gateway);
    /** @var \Drupal\commerce_payment\Entity\PaymentInterface $payment  */
    ['payment' => $payment] = $this->createMockPayment($order, $gateway);
    $payment->setState('authorization')->save();

    $gateway->getPlugin()->voidPayment($payment);
    static::assertEquals('authorization_voided', $payment->getState()->getId());
  }

  /**
   * @covers ::capturePayment
   */
  public function testCapturePaymentException() : void {
    $tokenBuilder = $this->prophesize(TokenRequestBuilderInterface::class);
    $tokenBuilder->tokenCommit(Argument::any(), Argument::any())
      ->willThrow($this->createRequestException());

    $gateway = $this->mockPaymentGateway(
      tokenPaymentRequestBuilder: $tokenBuilder->reveal(),
      plugin: 'paytrail_token',
    );
    $order = $this->createOrder($gateway);

    /** @var \Drupal\commerce_payment\Entity\PaymentInterface $payment  */
    ['payment' => $payment] = $this->createMockPayment($order, $gateway);
    $payment->setState('authorization')->save();

    $this->expectException(PaymentGatewayException::class);
    $gateway->getPlugin()->capturePayment($payment);
  }

  /**
   * @covers ::capturePayment
   */
  public function testCapturePaymentAmount() : void {
    $paymentRequestBuilder = $this->prophesize(PaymentRequestBuilderInterface::class);
    $paymentRequestBuilder
      ->get(Argument::any(), Argument::any())
      ->shouldBeCalled()
      ->willReturn(
        (new PaymentStatusResponse())
          ->setStatus('ok')
          ->setTransactionId('123')
      );
    $mitPaymentResponse = new MitPaymentResponse();
    $mitPaymentResponse->setTransactionId('123');
    $tokenBuilder = $this->prophesize(TokenRequestBuilderInterface::class);
    $tokenBuilder->tokenCommit(Argument::any(), Argument::any())
      ->willReturn($mitPaymentResponse);

    $gateway = $this->mockPaymentGateway(
      paymentRequestBuilder: $paymentRequestBuilder->reveal(),
      tokenPaymentRequestBuilder: $tokenBuilder->reveal(),
      plugin: 'paytrail_token',
    );

    // Test capture with NULL and specific amount.
    foreach (['22' => NULL, '1' => new Price('1', 'EUR')] as $expectedAmount => $price) {
      $order = $this->createOrder($gateway);

      /** @var \Drupal\commerce_payment\Entity\PaymentInterface $payment  */
      ['payment' => $payment] = $this->createMockPayment($order, $gateway);
      $payment->setState('authorization')->save();

      $gateway->getPlugin()->capturePayment($payment, $price);

      static::assertEquals('completed', $payment->getState()->getId());
      static::assertEquals('ok', $payment->getRemoteState());
      static::assertEquals($expectedAmount, $payment->getAmount()->getNumber());
    }
  }

}
