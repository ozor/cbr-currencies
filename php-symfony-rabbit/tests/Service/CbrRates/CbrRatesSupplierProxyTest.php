<?php
namespace App\Tests\Service\CbrRates;
use App\Dto\CbrRates\CbrRateDto;
use App\Dto\CbrRates\CbrRatesDto;
use App\Service\CbrRates\CbrRatesSupplier;
use App\Service\CbrRates\CbrRatesSupplierProxy;
use DateTimeImmutable;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
class CbrRatesSupplierProxyTest extends TestCase
{
    /** @var MockObject&CacheInterface */
    private CacheInterface $cache;
    /** @var MockObject&CbrRatesSupplier */
    private CbrRatesSupplier $supplier;
    private CbrRatesSupplierProxy $proxy;
    protected function setUp(): void
    {
        $this->cache = $this->createMock(CacheInterface::class);
        $this->supplier = $this->createMock(CbrRatesSupplier::class);
        $logger = $this->createMock(LoggerInterface::class);

        $this->proxy = new CbrRatesSupplierProxy(
            $this->cache,
            $this->supplier,
            $logger
        );
    }
    public function testGetDailyByDateReturnsCachedResult(): void
    {
        $date = new DateTimeImmutable('2023-10-25');
        $expectedRates = new CbrRatesDto($date, [
            new CbrRateDto('USD', 1, 75.0, 75.0),
        ]);
        $this->cache->expects($this->once())
            ->method('get')
            ->willReturn($expectedRates);
        $result = $this->proxy->getDailyByDate($date);
        $this->assertSame($expectedRates, $result);
    }
    public function testGetDailyByDateFetchesWhenCacheMiss(): void
    {
        $date = new DateTimeImmutable('2023-10-25');
        $expectedRates = new CbrRatesDto($date, [
            new CbrRateDto('USD', 1, 75.0, 75.0),
        ]);
        $this->supplier->expects($this->once())
            ->method('getDailyByDate')
            ->with($date)
            ->willReturn($expectedRates);
        $this->cache->expects($this->once())
            ->method('get')
            ->willReturnCallback(function ($key, $callback) {
                $item = $this->createMock(ItemInterface::class);
                return $callback($item);
            });
        $result = $this->proxy->getDailyByDate($date);
        $this->assertEquals($expectedRates, $result);
    }
}
