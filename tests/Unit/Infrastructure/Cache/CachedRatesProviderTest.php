<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Cache;

use App\Dto\CbrRates\CbrRateDto;
use App\Dto\CbrRates\CbrRatesDto;
use App\Infrastructure\Cache\CachedRatesProvider;
use App\Service\CbrRates\CbrRatesSupplier;
use App\Service\CbrRates\CbrHttpClient;
use App\Service\CbrRates\XmlRateParser;
use DateTimeImmutable;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class CachedRatesProviderTest extends TestCase
{
    /** @var MockObject&CacheInterface */
    private CacheInterface $cache;

    /** @var MockObject&XmlRateParser */
    private XmlRateParser $xmlRateParser;

    /** @var MockObject&LoggerInterface */
    private LoggerInterface $logger;

    private CachedRatesProvider $provider;

    protected function setUp(): void
    {
        $this->cache         = $this->createMock(CacheInterface::class);
        $cbrHttpClient = $this->createMock(CbrHttpClient::class);
        $this->xmlRateParser = $this->createMock(XmlRateParser::class);
        $innerProvider = new CbrRatesSupplier($cbrHttpClient, $this->xmlRateParser);
        $this->logger        = $this->createMock(LoggerInterface::class);

        $this->provider = new CachedRatesProvider(
            $this->cache,
            $innerProvider,
            $this->logger,
        );
    }

    // -------------------------------------------------------------------------
    // Cache hit — inner provider must NOT be called
    // -------------------------------------------------------------------------

    public function testCacheHitDoesNotCallInnerProvider(): void
    {
        $date          = new DateTimeImmutable('2024-03-15');
        $expectedRates = new CbrRatesDto($date, [
            new CbrRateDto('USD', 1, 90.0, 89.5),
            new CbrRateDto('RUR', 1, 1.0, 1.0),
        ]);

        $this->cache->expects($this->once())
            ->method('get')
            ->willReturn($expectedRates);

        // When cache returns the value, inner provider must not be exercised.
        $this->xmlRateParser->expects($this->never())->method('parse');

        $result = $this->provider->getDailyByDate($date);

        $this->assertEquals($expectedRates, $result);
    }

    // -------------------------------------------------------------------------
    // Cache miss — inner provider IS called, result returned
    // -------------------------------------------------------------------------

    public function testCacheMissCallsInnerProviderAndReturnsResult(): void
    {
        $date          = new DateTimeImmutable('2024-03-15');
        $parsedRates = new CbrRatesDto($date, [
            new CbrRateDto('USD', 1, 90.0, 89.5),
        ]);

        $expectedRates = new CbrRatesDto($date, [
            new CbrRateDto('USD', 1, 90.0, 89.5),
            new CbrRateDto('RUR', 1, 1.0, 1.0),
        ]);

        // Expect the XML parser to be called once (inner provider -> parser)
        $this->xmlRateParser->expects($this->once())
            ->method('parse')
            ->willReturn($parsedRates);

        $this->cache->expects($this->once())
            ->method('get')
            ->willReturnCallback(function (string $key, callable $callback) {
                $item = $this->createMock(ItemInterface::class);
                $item->expects($this->once())
                    ->method('expiresAfter')
                    ->with(86400);

                return $callback($item);
            });

        $result = $this->provider->getDailyByDate($date);

        $this->assertEquals($expectedRates, $result);
    }

    // -------------------------------------------------------------------------
    // Cache key contains the date
    // -------------------------------------------------------------------------

    public function testCacheKeyIncludesDate(): void
    {
        $date          = new DateTimeImmutable('2024-03-15');
        $expectedRates = new CbrRatesDto($date, []);

        $this->cache->expects($this->once())
            ->method('get')
            ->with($this->stringContains('2024-03-15'))
            ->willReturn($expectedRates);

        $this->provider->getDailyByDate($date);
    }

    // -------------------------------------------------------------------------
    // Fallback — cache layer throws, inner provider is called directly
    // -------------------------------------------------------------------------

    public function testFallbackToInnerProviderWhenCacheThrows(): void
    {
        $date          = new DateTimeImmutable('2024-03-15');
        $parsedRates = new CbrRatesDto($date, [
            new CbrRateDto('USD', 1, 90.0, 89.5),
        ]);

        $expectedRates = new CbrRatesDto($date, [
            new CbrRateDto('USD', 1, 90.0, 89.5),
            new CbrRateDto('RUR', 1, 1.0, 1.0),
        ]);

        $this->cache->expects($this->once())
            ->method('get')
            ->willThrowException(new RuntimeException('Cache unavailable'));

        // Inner provider path uses XmlRateParser internally — ensure it returns the parsed rates
        $this->xmlRateParser->expects($this->once())
            ->method('parse')
            ->willReturn($parsedRates);

        $this->logger->expects($this->atLeastOnce())
            ->method('error');

        $result = $this->provider->getDailyByDate($date);

        $this->assertEquals($expectedRates, $result);
    }

    // -------------------------------------------------------------------------
    // Null snapshot — inner provider returns null on cache miss
    // -------------------------------------------------------------------------

    public function testReturnsNullWhenInnerProviderReturnsNull(): void
    {
        $date = new DateTimeImmutable('2024-03-15');

        // Simulate cache returning null directly (no item and callback not invoked).
        $this->cache->expects($this->once())
            ->method('get')
            ->willReturn(null);

        $result = $this->provider->getDailyByDate($date);

        $this->assertNull($result);
    }
}
