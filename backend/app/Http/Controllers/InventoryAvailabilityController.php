<?php

namespace App\Http\Controllers;

use App\Http\Resources\InventoryAvailabilityResource;
use App\Services\InventoryAvailabilityService;
use Illuminate\Http\JsonResponse;

class InventoryAvailabilityController extends Controller
{
    public function __construct(private readonly InventoryAvailabilityService $inventoryAvailabilityService) {}

    public function index(): JsonResponse
    {
        $statuses = $this->inventoryAvailabilityService->getStatus();

        return response()->json([
            'success' => true,
            'message' => 'Inventory availability retrieved successfully',
            'data' => InventoryAvailabilityResource::collection($statuses),
        ]);
    }
}
