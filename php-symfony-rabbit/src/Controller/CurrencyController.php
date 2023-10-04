<?php

namespace App\Controller;

use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class CurrencyController extends AbstractController
{
    #[Route('/', name: 'app_currency', methods: ['GET'])]
    #[OA\Response(
        response: 200,
        description: 'OK',
    )]
    public function index(): JsonResponse
    {
        return $this->json([]);
    }
}
