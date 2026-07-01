<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Cache;

use App\Dto\CbrRates\CbrRateDto;
use App\Dto\CbrRates\CbrRatesDto;
use App\Infrastructure\Cache\CachedRatesProvider;
use App\Service\CbrRates\CbrRatesSupplier;
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

    /** @var MockObject&CbrRatesSupplier */
    private CbrRatesSupplier $innerProvider;

    /** @var MockObject&LoggerInterface */
    private LoggerInterface $logger;

    private CachedRatesProvider $provider;

    protected function setUp(): void
    {
        $this->cache         = $this->createMock(CacheInterface::class);
        $this->innerProvider = $this->createMock(CbrRatesSupplier::class);
        $this->logger        = $this->createMock(LoggerInterface::class);

        $this->provider = new CachedRatesProvider(
            $this->cache,
            $this->innerProvider,
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
        ]);

        $this->cache->expects($this->once())
            ->method('get')
            ->willReturn($expectedRates);

        $this->innerProvider->expects($this->never())
            ->method('getDailyByDate');

        $result = $this->provider->getDailyByDate($date);

        $this->assertSame($expectedRates, $result);
    }

    // -------------------------------------------------------------------------
    // Cache miss — inner provider IS called, result returned
    // -------------------------------------------------------------------------

    public function testCacheMissCallsInnerProviderAndReturnsResult(): void
    {
        $date          = new DateTimeImmutable('2024-03-15');
        $expectedRates = new CbrRatesDto($date, [
            new CbrRateDto('USD', 1, 90.0, 89.5),
        ]);

        $this->innerProvider->expects($this->once())
            ->method('getDailyByDate')
            ->with($date)
            ->willReturn($expectedRates);

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

        $this->assertSame($expectedRates, $result);
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
        $expectedRates = new CbrRatesDto($date, [
            new CbrRateDto('USD', 1, 90.0, 89.5),
        ]);

        $this->cache->expects($this->once())
            ->method('get')
            ->willThrowException(new RuntimeException('Cache unavailable'));

        $this->innerProvider->expects($this->once())
            ->method('getDailyByDate')
            ->with($date)
            ->willReturn($expectedRates);

        $this->logger->expects($this->atLeastOnce())
            ->method('error');

        $result = $this->provider->getDailyByDate($date);

        $this->assertSame($expectedRates, $result);
    }

    // -------------------------------------------------------------------------
    // Null snapshot — inner provider returns null on cache miss
    // -------------------------------------------------------------------------

    public function testReturnsNullWhenInnerProviderReturnsNull(): void
    {
        $date = new DateTimeImmutable('2024-03-15');

        $this->innerProvider->expects($this->once())
            ->method('getDailyByDate')
            ->willReturn(null);

        $this->cache->expects($this->once())
            ->method('get')
            ->willReturnCallback(function (string $key, callable $callback) {
                $item = $this->createMock(ItemInterface::class);
                $item->method('expiresAfter');

                return $callback($item);
            });

        $result = $this->provider->getDailyByDate($date);

        $this->assertNull($result);
    }
}
