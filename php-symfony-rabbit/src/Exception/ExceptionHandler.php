<?php

namespace App\Exception;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;

class ExceptionHandler
{
    public function handleApiException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();

        $message = $exception->getMessage();
        $code = $exception->getCode() ?: 404;
        if (empty($message)) {
            $message = ($code === 404)
                ? 'Rate not found.'
                : 'Something went wrong.';
            ;
        }

        $event->setResponse(
            new JsonResponse([
                'error' => $message,
            ], $code)
        );
    }
}