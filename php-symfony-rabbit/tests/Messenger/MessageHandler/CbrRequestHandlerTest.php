<?php

namespace App\Tests\Messenger\MessageHandler;

use App\Exception\CbrRates\CbrRateNotFoundException;
use App\Messenger\Message\CbrRatesRequestMessage;
use App\Messenger\MessageHandler\CbrRequestHandler;
use Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class CbrRequestHandlerTest extends TestCase
{
    /** @var MockObject&HttpClientInterface */
    private HttpClientInterface $httpClient;

    /** @var MockObject&LoggerInterface */
    private LoggerInterface $logger;

    private CbrRequestHandler $handler;

    protected function setUp(): void
    {
        $this->httpClient = $this->createMock(HttpClientInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->handler = new CbrRequestHandler(
            $this->httpClient,
            $this->logger
        );
    }

    public function testInvokeReturnsResponseContent(): void
    {
        $message = new CbrRatesRequestMessage('GET', '/test', ['date' => '25.10.2023']);
        $expectedContent = '<xml>test</xml>';

        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->once())
            ->method('getContent')
            ->willReturn($expectedContent);

        $this->httpClient->expects($this->once())
            ->method('request')
            ->with('GET', '/test', ['query' => ['date' => '25.10.2023']])
            ->willReturn($response);

        $result = ($this->handler)($message);

        $this->assertEquals($expectedContent, $result);
    }

    public function testInvokeThrowsExceptionOnHttpError(): void
    {
        $message = new CbrRatesRequestMessage('GET', '/test', []);

        $this->httpClient->expects($this->once())
            ->method('request')
            ->willThrowException(new Exception('HTTP error'));

        $this->logger->expects($this->once())
            ->method('error');

        $this->expectException(CbrRateNotFoundException::class);

        ($this->handler)($message);
    }
}
