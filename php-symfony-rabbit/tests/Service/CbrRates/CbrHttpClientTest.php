<?php

namespace App\Tests\Service\CbrRates;

use App\Config\CbrRates;
use App\Exception\CbrRates\CbrProviderException;
use App\Service\CbrRates\CbrHttpClient;
use DateTimeImmutable;
use Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class CbrHttpClientTest extends TestCase
{
    /** @var MockObject&HttpClientInterface */
    private HttpClientInterface $httpClient;

    /** @var MockObject&LoggerInterface */
    private LoggerInterface $logger;

    private CbrHttpClient $client;

    protected function setUp(): void
    {
        $this->httpClient = $this->createMock(HttpClientInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->client = new CbrHttpClient($this->httpClient, $this->logger);
    }

    public function testGetDailyXmlByDateReturnsContent(): void
    {
        $date = new DateTimeImmutable('2023-10-25');
        $expectedXml = '<ValCurs Date="25.10.2023"></ValCurs>';
        $expectedDateReq = $date->format(CbrRates::RATE_CBR_DATE_FORMAT);

        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->once())
            ->method('getContent')
            ->willReturn($expectedXml);

        $this->httpClient->expects($this->once())
            ->method('request')
            ->with('GET', '/scripts/XML_daily.asp', ['query' => ['date_req' => $expectedDateReq]])
            ->willReturn($response);

        $result = $this->client->getDailyXmlByDate($date);

        $this->assertSame($expectedXml, $result);
    }

    public function testGetDailyXmlByDateThrowsCbrProviderExceptionOnHttpError(): void
    {
        $date = new DateTimeImmutable('2023-10-25');

        $this->httpClient->expects($this->once())
            ->method('request')
            ->willThrowException(new Exception('Connection refused'));

        $this->logger->expects($this->once())
            ->method('error');

        $this->expectException(CbrProviderException::class);
        $this->expectExceptionMessage('CBR upstream unavailable.');

        $this->client->getDailyXmlByDate($date);
    }
}
