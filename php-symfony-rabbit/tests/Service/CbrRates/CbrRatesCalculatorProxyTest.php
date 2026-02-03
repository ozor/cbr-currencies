<?php

namespace App\Tests\Service\CbrRates;

use App\Dto\CbrRates\CbrRateRequestDto;
use App\Dto\CbrRates\CbrRateResponseDto;
use App\Dto\CbrRates\CbrRateResponsePropertyDto;
use App\Service\CbrRates\CbrRatesCalculator;
use App\Service\CbrRates\CbrRatesCalculatorProxy;
use DateTimeImmutable;
use DG\BypassFinals;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class CbrRatesCalculatorProxyTest extends TestCase
{
    /** @var MockObject&CacheInterface */
    private CacheInterface $cache;

    /** @var MockObject&CbrRatesCalculator */
    private CbrRatesCalculator $calculator;

    /** @var MockObject&LoggerInterface */
    private LoggerInterface $logger;

    private CbrRatesCalculatorProxy $proxy;

    protected function setUp(): void
    {
        BypassFinals::enable();

        $this->cache = $this->createMock(CacheInterface::class);
        $this->calculator = $this->createMock(CbrRatesCalculator::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->proxy = new CbrRatesCalculatorProxy(
            $this->cache,
            $this->calculator,
            $this->logger
        );
    }

    public function testCalculateReturnsCachedResult(): void
    {
        $requestDto = new CbrRateRequestDto('25.10.2023', 'USD', 'EUR');
        $expectedResponse = new CbrRateResponseDto(
            new DateTimeImmutable('2023-10-25'),
            new CbrRateResponsePropertyDto('USD', 75.0, 74.5, 0.5),
            new CbrRateResponsePropertyDto('EUR', 85.0, 84.5, 0.5),
            new CbrRateResponsePropertyDto('USD/EUR', 0.8824, 0.8817, 0.0007)
        );

        $this->logger->expects($this->once())
            ->method('info')
            ->with('Attempting to calculate rates from cache');

        $this->cache->expects($this->once())
            ->method('get')
            ->willReturn($expectedResponse);

        $result = $this->proxy->calculate($requestDto);

        $this->assertSame($expectedResponse, $result);
    }

    public function testCalculateComputesWhenCacheMiss(): void
    {
        $requestDto = new CbrRateRequestDto('25.10.2023', 'USD', 'EUR');
        $expectedResponse = new CbrRateResponseDto(
            new DateTimeImmutable('2023-10-25'),
            new CbrRateResponsePropertyDto('USD', 75.0, 74.5, 0.5),
            new CbrRateResponsePropertyDto('EUR', 85.0, 84.5, 0.5),
            new CbrRateResponsePropertyDto('USD/EUR', 0.8824, 0.8817, 0.0007)
        );

        $this->logger->expects($this->exactly(3))
            ->method('info');

        $this->calculator->expects($this->once())
            ->method('calculate')
            ->with($requestDto)
            ->willReturn($expectedResponse);

        $this->cache->expects($this->once())
            ->method('get')
            ->willReturnCallback(function ($key, $callback) use ($expectedResponse) {
                $item = $this->createMock(ItemInterface::class);
                $item->expects($this->once())
                    ->method('expiresAfter')
                    ->with(86400);

                return $callback($item);
            });

        $result = $this->proxy->calculate($requestDto);

        $this->assertEquals($expectedResponse, $result);
    }

    public function testCalculateFallbackWhenCacheThrowsException(): void
    {
        $requestDto = new CbrRateRequestDto('25.10.2023', 'USD', 'EUR');
        $expectedResponse = new CbrRateResponseDto(
            new DateTimeImmutable('2023-10-25'),
            new CbrRateResponsePropertyDto('USD', 75.0, 74.5, 0.5),
            new CbrRateResponsePropertyDto('EUR', 85.0, 84.5, 0.5),
            new CbrRateResponsePropertyDto('USD/EUR', 0.8824, 0.8817, 0.0007)
        );

        $this->logger->expects($this->once())
            ->method('info');

        $this->logger->expects($this->once())
            ->method('error')
            ->with('Cache error, falling back to direct calculator call');

        $cacheException = new RuntimeException('Cache error');

        $this->cache->expects($this->once())
            ->method('get')
            ->willThrowException($cacheException);

        $this->calculator->expects($this->once())
            ->method('calculate')
            ->with($requestDto)
            ->willReturn($expectedResponse);

        $result = $this->proxy->calculate($requestDto);

        $this->assertEquals($expectedResponse, $result);
    }
}
