<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Contract\CbrRatesCalculatorInterface;
use App\Dto\CbrRates\CbrRateResponseDto;
use App\Dto\CbrRates\CbrRateResponsePropertyDto;
use App\Exception\CbrRates\ParseRatesException;
use App\Exception\CbrRates\PreviousTradingDayNotFoundException;
use App\Exception\CbrRates\RateNotFoundException;
use App\Exception\CbrRates\UpstreamUnavailableException;
use App\Exception\ErrorCode;
use DateTimeImmutable;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Functional tests for the CBR rates API endpoint.
 *
 * Uses WebTestCase + container->set() to inject mock calculator.
 * No real HTTP requests to CBR are made.
 *
 * Covers:
 *  - happy path 200 + expected JSON structure
 *  - validation errors → 400
 *  - rate not found → 404
 *  - previous trading day not found → 404
 *  - upstream unavailable → 502
 *  - parse failure → 502
 *  - response content-type contract
 */
class CbrControllerFunctionalTest extends WebTestCase
{
    private const string VALID_DATE = '2025-01-15';
    private const string VALID_CODE = 'USD';
    private const string VALID_BASE = 'EUR';
    private const string BASE_URL   = '/api/v1/cbr/rates';

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /** @return MockObject&CbrRatesCalculatorInterface */
    private function mockCalculator(): MockObject
    {
        return $this->createMock(CbrRatesCalculatorInterface::class);
    }

    private function url(string $date, string $code, string $base = self::VALID_BASE): string
    {
        return sprintf('%s/%s/%s/%s', self::BASE_URL, $date, $code, $base);
    }

    private function assertErrorResponse(
        KernelBrowser $client,
        int           $expectedStatus,
        string        $expectedCode
    ): void {
        $this->assertSame($expectedStatus, $client->getResponse()->getStatusCode());

        $body = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('error', $body, 'Response must have root key "error"');
        $this->assertSame($expectedCode, $body['error']['code']);
        $this->assertArrayHasKey('message', $body['error']);
    }

    // -------------------------------------------------------------------------
    // 200 — Happy path
    // -------------------------------------------------------------------------

    public function testHappyPathReturns200WithCorrectJsonStructure(): void
    {
        $client     = static::createClient();
        $calculator = $this->mockCalculator();

        $responseDto = new CbrRateResponseDto(
            new DateTimeImmutable(self::VALID_DATE),
            new CbrRateResponsePropertyDto('USD', 75.0, 74.5, 0.5),
            new CbrRateResponsePropertyDto('EUR', 85.0, 84.5, 0.5),
            new CbrRateResponsePropertyDto('USD/EUR', 0.8824, 0.8817, 0.0007)
        );

        $calculator->method('calculate')->willReturn($responseDto);
        static::getContainer()->set(CbrRatesCalculatorInterface::class, $calculator);

        $client->request('GET', $this->url(self::VALID_DATE, self::VALID_CODE));

        $this->assertSame(200, $client->getResponse()->getStatusCode());

        $body = json_decode($client->getResponse()->getContent(), true);

        // Top-level keys
        $this->assertArrayHasKey('date', $body);
        $this->assertArrayHasKey('rate', $body);
        $this->assertArrayHasKey('baseRate', $body);
        $this->assertArrayHasKey('crossRate', $body);

        // date value
        $this->assertSame(self::VALID_DATE, $body['date']);

        // rate fields
        foreach (['rate', 'baseRate', 'crossRate'] as $section) {
            $this->assertArrayHasKey('code', $body[$section], "$section must have 'code'");
            $this->assertArrayHasKey('value', $body[$section], "$section must have 'value'");
            $this->assertArrayHasKey('valuePrev', $body[$section], "$section must have 'valuePrev'");
            $this->assertArrayHasKey('diff', $body[$section], "$section must have 'diff'");
        }

        $this->assertSame('USD', $body['rate']['code']);
        $this->assertSame('EUR', $body['baseRate']['code']);
        $this->assertSame('USD/EUR', $body['crossRate']['code']);
    }

    public function testHappyPathResponseIsJson(): void
    {
        $client     = static::createClient();
        $calculator = $this->mockCalculator();

        $responseDto = new CbrRateResponseDto(
            new DateTimeImmutable(self::VALID_DATE),
            new CbrRateResponsePropertyDto('USD', 75.0, 74.5, 0.5),
            new CbrRateResponsePropertyDto('EUR', 85.0, 84.5, 0.5),
            new CbrRateResponsePropertyDto('USD/EUR', 0.8824, 0.8817, 0.0007)
        );

        $calculator->method('calculate')->willReturn($responseDto);
        static::getContainer()->set(CbrRatesCalculatorInterface::class, $calculator);

        $client->request('GET', $this->url(self::VALID_DATE, self::VALID_CODE));

        $this->assertStringContainsString(
            'application/json',
            $client->getResponse()->headers->get('Content-Type')
        );
    }

    // -------------------------------------------------------------------------
    // 400 — Validation errors
    // -------------------------------------------------------------------------

    public function testInvalidDateFormatReturns400(): void
    {
        $client = static::createClient();

        $client->request('GET', $this->url('not-a-date', self::VALID_CODE));

        $this->assertErrorResponse($client, 400, ErrorCode::VALIDATION_ERROR->value);

        $body = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('details', $body['error'], 'Validation error must include details');
    }

    public function testInvalidCurrencyCodeReturns400(): void
    {
        $client = static::createClient();

        $client->request('GET', $this->url(self::VALID_DATE, 'NOTACURRENCY'));

        $this->assertErrorResponse($client, 400, ErrorCode::VALIDATION_ERROR->value);

        $body = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('details', $body['error']);
    }

    // -------------------------------------------------------------------------
    // 404 — Business not found
    // -------------------------------------------------------------------------

    public function testRateNotFoundReturns404(): void
    {
        $client     = static::createClient();
        $calculator = $this->mockCalculator();
        $calculator->method('calculate')->willThrowException(new RateNotFoundException());
        static::getContainer()->set(CbrRatesCalculatorInterface::class, $calculator);

        $client->request('GET', $this->url(self::VALID_DATE, self::VALID_CODE));

        $this->assertErrorResponse($client, 404, ErrorCode::RATE_NOT_FOUND->value);
        $body = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayNotHasKey('details', $body['error']);
    }

    public function testPreviousTradingDayNotFoundReturns404(): void
    {
        $client     = static::createClient();
        $calculator = $this->mockCalculator();
        $calculator->method('calculate')->willThrowException(new PreviousTradingDayNotFoundException());
        static::getContainer()->set(CbrRatesCalculatorInterface::class, $calculator);

        $client->request('GET', $this->url(self::VALID_DATE, self::VALID_CODE));

        $this->assertErrorResponse($client, 404, ErrorCode::RATE_NOT_FOUND->value);
        $body = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayNotHasKey('details', $body['error']);
    }

    // -------------------------------------------------------------------------
    // 502 — Upstream / parse failures
    // -------------------------------------------------------------------------

    public function testUpstreamUnavailableReturns502(): void
    {
        $client     = static::createClient();
        $calculator = $this->mockCalculator();
        $calculator->method('calculate')->willThrowException(
            new UpstreamUnavailableException('CBR upstream unavailable.')
        );
        static::getContainer()->set(CbrRatesCalculatorInterface::class, $calculator);

        $client->request('GET', $this->url(self::VALID_DATE, self::VALID_CODE));

        $this->assertErrorResponse($client, 502, ErrorCode::UPSTREAM_UNAVAILABLE->value);
        $body = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayNotHasKey('details', $body['error']);
    }

    public function testParseFailureReturns502(): void
    {
        $client     = static::createClient();
        $calculator = $this->mockCalculator();
        $calculator->method('calculate')->willThrowException(
            new ParseRatesException('Failed to parse CBR rates XML.')
        );
        static::getContainer()->set(CbrRatesCalculatorInterface::class, $calculator);

        $client->request('GET', $this->url(self::VALID_DATE, self::VALID_CODE));

        $this->assertErrorResponse($client, 502, ErrorCode::PARSE_ERROR->value);
        $body = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayNotHasKey('details', $body['error']);
    }

    // -------------------------------------------------------------------------
    // JSON structure contract
    // -------------------------------------------------------------------------

    public function testErrorResponseHasCorrectContentType(): void
    {
        $client = static::createClient();
        $client->request('GET', $this->url('bad-date', self::VALID_CODE));

        $this->assertStringContainsString(
            'application/json',
            $client->getResponse()->headers->get('Content-Type')
        );
    }
}
