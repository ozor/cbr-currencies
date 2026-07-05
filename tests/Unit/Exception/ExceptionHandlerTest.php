<?php

declare(strict_types=1);

namespace App\Tests\Unit\Exception;

use App\Exception\CbrRates\ParseRatesException;
use App\Exception\CbrRates\PreviousTradingDayNotFoundException;
use App\Exception\CbrRates\RateNotFoundException;
use App\Exception\CbrRates\UpstreamUnavailableException;
use App\Exception\ErrorCode;
use App\Exception\ExceptionHandler;
use App\Exception\ValidationException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class ExceptionHandlerTest extends TestCase
{
    /** @var MockObject&LoggerInterface */
    private LoggerInterface $logger;

    private ExceptionHandler $handler;

    protected function setUp(): void
    {
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->handler = new ExceptionHandler($this->logger);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeEvent(\Throwable $exception): ExceptionEvent
    {
        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = Request::create('/api/v1/cbr/rates/2025-01-15/USD');

        return new ExceptionEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $exception);
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeResponse(ExceptionEvent $event): array
    {
        $response = $event->getResponse();
        $this->assertNotNull($response);

        return json_decode((string) $response->getContent(), true);
    }

    // -------------------------------------------------------------------------
    // ValidationException → 400
    // -------------------------------------------------------------------------

    public function testValidationExceptionReturns400(): void
    {
        $exception = new ValidationException(['date' => 'Wrong date format.']);
        $event = $this->makeEvent($exception);

        $this->logger->expects($this->never())->method('error');

        $this->handler->handleApiException($event);

        $response = $event->getResponse();
        $this->assertNotNull($response);
        $this->assertSame(400, $response->getStatusCode());

        $body = $this->decodeResponse($event);
        $this->assertSame(ErrorCode::VALIDATION_ERROR->value, $body['error']['code']);
        $this->assertSame('Request validation error', $body['error']['message']);
        $this->assertSame(['date' => 'Wrong date format.'], $body['error']['details']);
    }

    public function testValidationExceptionDetailsContainFieldErrors(): void
    {
        $errors = ['code' => 'This value is not a valid currency.', 'date' => 'Wrong date format.'];
        $exception = new ValidationException($errors);
        $event = $this->makeEvent($exception);

        $this->handler->handleApiException($event);

        $body = $this->decodeResponse($event);
        $this->assertSame($errors, $body['error']['details']);
    }

    // -------------------------------------------------------------------------
    // RateNotFoundException → 404
    // -------------------------------------------------------------------------

    public function testRateNotFoundExceptionReturns404(): void
    {
        $exception = new RateNotFoundException();
        $event = $this->makeEvent($exception);

        $this->logger->expects($this->never())->method('error');

        $this->handler->handleApiException($event);

        $response = $event->getResponse();
        $this->assertNotNull($response);
        $this->assertSame(404, $response->getStatusCode());

        $body = $this->decodeResponse($event);
        $this->assertSame(ErrorCode::RATE_NOT_FOUND->value, $body['error']['code']);
        $this->assertArrayNotHasKey('details', $body['error']);
    }

    // -------------------------------------------------------------------------
    // PreviousTradingDayNotFoundException → 404
    // -------------------------------------------------------------------------

    public function testPreviousTradingDayNotFoundReturns404WithRateNotFoundCode(): void
    {
        $exception = new PreviousTradingDayNotFoundException();
        $event = $this->makeEvent($exception);

        $this->handler->handleApiException($event);

        $response = $event->getResponse();
        $this->assertNotNull($response);
        $this->assertSame(404, $response->getStatusCode());

        $body = $this->decodeResponse($event);
        $this->assertSame(ErrorCode::RATE_NOT_FOUND->value, $body['error']['code']);
    }

    // -------------------------------------------------------------------------
    // UpstreamUnavailableException → 502
    // -------------------------------------------------------------------------

    public function testUpstreamUnavailableExceptionReturns502(): void
    {
        $exception = new UpstreamUnavailableException('CBR upstream unavailable.');
        $event = $this->makeEvent($exception);

        $this->logger->expects($this->once())->method('error');

        $this->handler->handleApiException($event);

        $response = $event->getResponse();
        $this->assertNotNull($response);
        $this->assertSame(502, $response->getStatusCode());

        $body = $this->decodeResponse($event);
        $this->assertSame(ErrorCode::UPSTREAM_UNAVAILABLE->value, $body['error']['code']);
        $this->assertArrayNotHasKey('details', $body['error']);
    }

    // -------------------------------------------------------------------------
    // ParseRatesException → 502
    // -------------------------------------------------------------------------

    public function testParseRatesExceptionReturns502(): void
    {
        $exception = new ParseRatesException('Failed to parse CBR rates XML.');
        $event = $this->makeEvent($exception);

        $this->logger->expects($this->once())->method('error');

        $this->handler->handleApiException($event);

        $response = $event->getResponse();
        $this->assertNotNull($response);
        $this->assertSame(502, $response->getStatusCode());

        $body = $this->decodeResponse($event);
        $this->assertSame(ErrorCode::PARSE_ERROR->value, $body['error']['code']);
        $this->assertArrayNotHasKey('details', $body['error']);
    }

    // -------------------------------------------------------------------------
    // Unknown exception → 500
    // -------------------------------------------------------------------------

    public function testUnknownExceptionReturns500(): void
    {
        $exception = new \RuntimeException('Unexpected error');
        $event = $this->makeEvent($exception);

        $this->logger->expects($this->once())->method('error');

        $this->handler->handleApiException($event);

        $response = $event->getResponse();
        $this->assertNotNull($response);
        $this->assertSame(500, $response->getStatusCode());

        $body = $this->decodeResponse($event);
        $this->assertSame(ErrorCode::INTERNAL_ERROR->value, $body['error']['code']);
        $this->assertArrayNotHasKey('details', $body['error']);
    }

    // -------------------------------------------------------------------------
    // JSON structure contract
    // -------------------------------------------------------------------------

    public function testResponseAlwaysHasErrorRootKey(): void
    {
        foreach ([
            new ValidationException([]),
            new RateNotFoundException(),
            new UpstreamUnavailableException(),
            new ParseRatesException(),
            new \RuntimeException('oops'),
        ] as $exception) {
            $event = $this->makeEvent($exception);
            $this->handler->handleApiException($event);

            $body = $this->decodeResponse($event);
            $this->assertArrayHasKey('error', $body, 'Root key "error" must always be present');
            $this->assertArrayHasKey('code', $body['error']);
            $this->assertArrayHasKey('message', $body['error']);
        }
    }

    public function testResponseContentTypeIsJson(): void
    {
        $event = $this->makeEvent(new RateNotFoundException());
        $this->handler->handleApiException($event);

        $this->assertStringContainsString(
            'application/json',
            (string) $event->getResponse()?->headers->get('Content-Type')
        );
    }
}
