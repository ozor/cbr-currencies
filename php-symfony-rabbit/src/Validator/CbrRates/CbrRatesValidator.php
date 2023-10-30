<?php

namespace App\Validator\CbrRates;

use App\Dto\CbrRates\CbrRateRequestDto;
use App\Exception\RequestValidationException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

readonly class CbrRatesValidator implements CbrRatesValidatorInterface
{
    public function __construct(
        private ValidatorInterface $symfonyValidator,
    ) {
    }

    public function validate(CbrRateRequestDto $dto): void
    {
        $violations = $this->symfonyValidator->validate($dto);

        if (count($violations) > 0) {
            $validationErrors = [];
            foreach ($violations as $violation) {
                $validationErrors[$violation->getPropertyPath()] = $violation->getMessage();
            }

            throw new RequestValidationException($validationErrors);
        }
    }
}