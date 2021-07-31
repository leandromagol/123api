<?php

namespace App\Http\Controllers\API;

use App\Services\FlightsService;

class FlightsController
{
    private $flightsService;

    public function __construct(FlightsService $flightsService)
    {
        $this->flightsService = $flightsService;
    }

    public function index(): \Illuminate\Http\JsonResponse
    {
        return response()->json($this->flightsService->getFlights(),200);
    }
}
