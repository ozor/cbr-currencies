<?php

namespace App\Controller;

use App\Config\CbrRates;
use App\Contract\CbrRatesCalculatorInterface;
use App\Dto\CbrRates\CbrRateRequestDto;
use App\Validator\CbrRates\CbrRatesValidatorInterface;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/cbr')]
class CbrController extends AbstractController
{
    #[Route('/rates/{date}/{code}/{baseCode}', name: 'app_currency', methods: ['GET'])]
    #[OA\Get(
        path: '/',
        description: 'Get currency rate',
        parameters: [
            new OA\Parameter(
                name: 'date',
                description: 'Rate date',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string'),
            ),
            new OA\Parameter(
                name: 'code',
                description: 'Currency code',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string'),
            ),
            new OA\Parameter(
                name: 'baseCode',
                description: 'Base currency code',
                in: 'path',
                required: false,
                schema: new OA\Schema(type: 'string'),
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'OK',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'date', type: 'string', example: '20/10/2021'),
                        new OA\Property(property: 'rate', properties: [
                            new OA\Property(property: 'code', description: 'Rate currency code', type: 'string', example: 'USD'),
                            new OA\Property(property: 'value', description: 'Rate value', type: 'number', example: 1.2345),
                            new OA\Property(property: 'valuePrev', description: 'Previous day rate value', type: 'number', example: 1.2345),
                            new OA\Property(property: 'diff', description: 'Values difference with date and previous day', type: 'number', example: 0.0001),
                        ], type: 'object'),
                        new OA\Property(property: 'baseRate', properties: [
                            new OA\Property(property: 'code', description: 'Base rate currency code', type: 'string', example: 'JPY'),
                            new OA\Property(property: 'value', description: 'Base rate value', type: 'number', example: 1.2345),
                            new OA\Property(property: 'valuePrev', description: 'Previous day base rate value', type: 'number', example: 1.2345),
                            new OA\Property(property: 'diff', description: 'Values difference with date and previous day', type: 'number', example: 0.0001),
                        ], type: 'object'),
                        new OA\Property(property: 'crossRate', properties: [
                            new OA\Property(property: 'code', description: 'Cross rate currencies pair code', type: 'string', example: 'USD/JPY'),
                            new OA\Property(property: 'value', description: 'Cross rate value', type: 'number', example: 1.2345),
                            new OA\Property(property: 'valuePrev', description: 'Previous day cross rate value', type: 'number', example: 1.2345),
                            new OA\Property(property: 'diff', description: 'Values difference with date and previous day', type: 'number', example: 0.0001),
                        ], type: 'object'),
                    ],
                    type: 'object'
                ),
            ),
            new OA\Response(
                response: 400,
                description: 'Bad request',
            ),
            new OA\Response(
                response: 404,
                description: 'Not found',
            ),
            new OA\Response(
                response: 500,
                description: 'Internal error',
            ),
        ]
    )]
    public function rates(
        CbrRatesValidatorInterface $validator,
        CbrRatesCalculatorInterface $calculator,
        string $date,
        string $code,
        string $baseCode = CbrRates::BASE_CURRENCY_CODE_DEFAULT,
    ): JsonResponse {
        $rateRequestDto = new CbrRateRequestDto($date, $code, $baseCode);
        $validator->validate($rateRequestDto);

        return $this->json(
            $calculator->calculate($rateRequestDto)->toArray()
        );
    }
}
