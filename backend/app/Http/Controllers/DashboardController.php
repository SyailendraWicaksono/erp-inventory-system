<?php

namespace App\Http\Controllers;

use App\Http\Resources\DashboardInventoryResource;
use App\Http\Resources\DashboardOrderResource;
use App\Http\Resources\DashboardPaymentResource;
use App\Http\Resources\DashboardProductionResource;
use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function __construct(private readonly DashboardService $dashboardService) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Dashboard retrieved successfully',
            'data' => [
                'orders' => (new DashboardOrderResource($this->dashboardService->getOrdersSummary()))->resolve(),
                'production' => (new DashboardProductionResource($this->dashboardService->getProductionSummary()))->resolve(),
                'inventory' => (new DashboardInventoryResource($this->dashboardService->getInventorySummary()))->resolve(),
                'payments' => (new DashboardPaymentResource($this->dashboardService->getPaymentsSummary()))->resolve(),
            ],
        ]);
    }

    public function orders(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Orders summary retrieved successfully',
            'data' => new DashboardOrderResource($this->dashboardService->getOrdersSummary()),
        ]);
    }

    public function production(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Production summary retrieved successfully',
            'data' => new DashboardProductionResource($this->dashboardService->getProductionSummary()),
        ]);
    }

    public function inventory(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Inventory summary retrieved successfully',
            'data' => new DashboardInventoryResource($this->dashboardService->getInventorySummary()),
        ]);
    }

    public function payments(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Payments summary retrieved successfully',
            'data' => new DashboardPaymentResource($this->dashboardService->getPaymentsSummary()),
        ]);
    }
}
