<?php

declare(strict_types=1);

namespace App\Exception;

use App\Exception\CbrRates\ParseRatesException;
use App\Exception\CbrRates\PreviousTradingDayNotFoundException;
use App\Exception\CbrRates\RateNotFoundException;
use App\Exception\CbrRates\UpstreamUnavailableException;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Throwable;

readonly class ExceptionHandler
{
    public function __construct(
        private LoggerInterface $logger,
    ) {
    }

    public function handleApiException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();

        [$httpStatus, $errorCode] = $this->resolveStatusAndCode($exception);

        if ($httpStatus >= 500) {
            $this->logger->error($exception->getMessage(), [
                'exception' => $exception,
                'method'    => __METHOD__,
            ]);
        }

        $error = [
            'code'    => $errorCode->value,
            'message' => $exception->getMessage(),
        ];

        if ($exception instanceof ValidationException) {
            $error['details'] = $exception->getErrors();
        }

        $event->setResponse(new JsonResponse(['error' => $error], $httpStatus));
    }

    /**
     * @return array{int, ErrorCode}
     */
    private function resolveStatusAndCode(Throwable $exception): array
    {
        return match (true) {
            $exception instanceof ValidationException                 => [400, ErrorCode::VALIDATION_ERROR],
            $exception instanceof RateNotFoundException,
            $exception instanceof PreviousTradingDayNotFoundException => [404, ErrorCode::RATE_NOT_FOUND],
            $exception instanceof UpstreamUnavailableException        => [502, ErrorCode::UPSTREAM_UNAVAILABLE],
            $exception instanceof ParseRatesException                 => [502, ErrorCode::PARSE_ERROR],
            default                                                   => [500, ErrorCode::INTERNAL_ERROR],
        };
    }
}
