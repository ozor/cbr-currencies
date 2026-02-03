<?php

namespace App\Tests\Validator\CbrRates;

use App\Dto\CbrRates\CbrRateRequestDto;
use App\Exception\RequestValidationException;
use App\Validator\CbrRates\CbrRatesValidator;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class CbrRatesValidatorTest extends TestCase
{
    /** @var MockObject&ValidatorInterface */
    private ValidatorInterface $symfonyValidator;

    private CbrRatesValidator $validator;

    protected function setUp(): void
    {
        $this->symfonyValidator = $this->createMock(ValidatorInterface::class);
        $this->validator = new CbrRatesValidator($this->symfonyValidator);
    }

    public function testValidatePassesWithNoViolations(): void
    {
        $dto = new CbrRateRequestDto('25.10.2023', 'USD', 'EUR');

        $this->symfonyValidator->expects($this->once())
            ->method('validate')
            ->with($dto)
            ->willReturn(new ConstraintViolationList());

        $this->validator->validate($dto);

        $this->assertTrue(true);
    }

    public function testValidateThrowsExceptionWithViolations(): void
    {
        $dto = new CbrRateRequestDto('invalid-date', 'USD', 'EUR');

        $violation = new ConstraintViolation(
            'Invalid date format',
            null,
            [],
            $dto,
            'date',
            'invalid-date'
        );

        $violations = new ConstraintViolationList([$violation]);

        $this->symfonyValidator->expects($this->once())
            ->method('validate')
            ->with($dto)
            ->willReturn($violations);

        $this->expectException(RequestValidationException::class);

        $this->validator->validate($dto);
    }
}
