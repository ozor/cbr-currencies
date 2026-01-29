<?php

namespace App\Exception;

use App\Exception\CbrRates\CbrRatesExceptionInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;

readonly class ExceptionHandler
{
    public function __construct(
        private LoggerInterface $logger,
    ) {
    }

    public function handleApiException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();
        $code = $exception->getCode() ?: 404;
        $message = $exception->getMessage() ?: ($code === 404 ? 'Rate not found.' : 'Something went wrong.');

        if (!($exception instanceof CbrRatesExceptionInterface)) {
            $this->logger->error($exception->getMessage(), [
                'exception' => $exception,
                'method' => __METHOD__,
            ]);
        }

        if ($exception instanceof RequestValidationException) {
            $event->setResponse(
                new JsonResponse([
                    'errorMessage' => $message,
                    'errors' => $exception->getErrors()
                ], $code)
            );
            return;
        }

        $event->setResponse(
            new JsonResponse([
                'errorMessage' => $message,
            ], $code)
        );
    }
}