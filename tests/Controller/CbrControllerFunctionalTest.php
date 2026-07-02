<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Contract\CbrRatesCalculatorInterface;
use App\Exception\CbrRates\ParseRatesException;
use App\Exception\CbrRates\RateNotFoundException;
use App\Exception\CbrRates\UpstreamUnavailableException;
use App\Exception\ErrorCode;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Functional tests for API error scenarios.
 *
 * Uses WebTestCase + container->set() to inject mock calculator.
 * No real HTTP requests to CBR are made.
 */
class CbrControllerFunctionalTest extends WebTestCase
{
    private const string VALID_DATE     = '2025-01-15';
    private const string VALID_CODE     = 'USD';
    private const string VALID_BASE     = 'EUR';
    private const string BASE_URL       = '/api/v1/cbr/rates';

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
        \Symfony\Bundle\FrameworkBundle\KernelBrowser $client,
        int    $expectedStatus,
        string $expectedCode
    ): void {
        $this->assertSame($expectedStatus, $client->getResponse()->getStatusCode());

        $body = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('error', $body, 'Response must have root key "error"');
        $this->assertSame($expectedCode, $body['error']['code']);
        $this->assertArrayHasKey('message', $body['error']);
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
    // 404 — Rate not found (business)
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

    // -------------------------------------------------------------------------
    // 502 — Upstream unavailable
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

    // -------------------------------------------------------------------------
    // 502 — Parse failure
    // -------------------------------------------------------------------------

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
