<?php

namespace App\Http\Controllers\API;

use App\Services\FlightsService;

class FlightsController
{
    private $flightsService;
    /**
     * @OA\Get(
     *     path="/api/flights",
     *     description="Get flights",
     *     @OA\Response(response="default", description="Return all flights, flights organized in groups with the same price and fare")
     * )
     */
    public function __construct(FlightsService $flightsService)
    {
        $this->flightsService = $flightsService;
    }

    public function index(): \Illuminate\Http\JsonResponse
    {
        return response()->json($this->flightsService->getFlights(),200);
    }
}
