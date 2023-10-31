<?php

namespace App\Controller;

use App\Config\CbrRates;
use App\Contract\CbrRatesCalculatorInterface;
use App\Dto\CbrRates\CbrRateRequestDto;
use App\Validator\CbrRates\CbrRatesValidatorInterface;
use OpenApi\Annotations as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/cbr')]
class CbrController extends AbstractController
{
    /**
     * @OA\Get(
     *      path="/cbr/rates/{date}/{code}/{baseCode}",
     *      description="Get currency rate"
     * )
     * @OA\Parameter(
     *      name="date",
     *      description="Rate date",
     *      in="path",
     *      required=true,
     *      @OA\Schema(type="string")
     * ),
     * @OA\Parameter(
     *      name="code",
     *      description="Currency code",
     *      in="path",
     *      required=true,
     *      @OA\Schema(type="string")
     * ),
     * @OA\Parameter(
     *      name="baseCode",
     *      description="Base currency code",
     *      in="path",
     *      required=false,
     *      @OA\Schema(type="string")
     * ),
     * @OA\Response(
     *      response=200,
     *      description="OK",
     *      @OA\JsonContent(
     *          @OA\Property(property="date", type="string", example="20/10/2021"),
     *          @OA\Property(property="rate", type="object",
     *              @OA\Property(property="code", description="Rate currency code", type="string", example="USD"),
     *              @OA\Property(property="value", description="Rate value", type="number", example=1.2345),
     *              @OA\Property(property="valuePrev", description="Previous day rate value", type="number", example=1.2345),
     *              @OA\Property(property="diff", description="Values difference with date and previous day", type="number", example=0.0001),
     *          ),
     *          @OA\Property(property="baseRate", type="object",
     *              @OA\Property(property="code", description="Base rate currency code", type="string", example="JPY"),
     *              @OA\Property(property="value", description="Base rate value", type="number", example=1.2345),
     *              @OA\Property(property="valuePrev", description="Previous day base rate value", type="number", example=1.2345),
     *              @OA\Property(property="diff", description="Values difference with date and previous day", type="number", example=0.0001),
     *          ),
     *          @OA\Property(property="crossRate", type="object",
     *              @OA\Property(property="code", description="Cross rate currencies pair code", type="string", example="USD/JPY"),
     *              @OA\Property(property="value", description="Cross rate value", type="number", example=1.2345),
     *              @OA\Property(property="valuePrev", description="Previous day cross rate value", type="number", example=1.2345),
     *              @OA\Property(property="diff", description="Values difference with date and previous day", type="number", example=0.0001),
     *          )
     *      )
     * ),
     * @OA\Response(
     *    response=400,
     *    description="Bad request",
     * ),
     * @OA\Response(
     *    response=404,
     *    description="Not found",
     * ),
     * @OA\Response(
     *    response=500,
     *    description="Internal error",
     * )
     */
    #[Route('/rates/{date}/{code}/{baseCode}', name: 'app_currency', methods: ['GET'])]
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
