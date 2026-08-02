<?php

namespace App\Http\Controllers;

use App\Http\Requests\InventoryPurchaseRequest;
use App\Http\Resources\InventoryPurchaseResource;
use App\Services\InventoryPurchaseService;
use Illuminate\Http\JsonResponse;

class InventoryPurchaseController extends Controller
{
    public function __construct(private readonly InventoryPurchaseService $inventoryPurchaseService)
    {
    }

    public function index(): JsonResponse
    {
        $purchases = $this->inventoryPurchaseService->getAll();

        return response()->json([
            'success' => true,
            'message' => 'Inventory purchases retrieved successfully',
            'data' => InventoryPurchaseResource::collection($purchases),
        ]);
    }

    public function store(InventoryPurchaseRequest $request): JsonResponse
    {
        $purchase = $this->inventoryPurchaseService->create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Inventory purchase created successfully',
            'data' => new InventoryPurchaseResource($purchase),
        ], 201);
    }

    public function show(int $id): JsonResponse
    {
        $purchase = $this->inventoryPurchaseService->getById($id);

        return response()->json([
            'success' => true,
            'message' => 'Inventory purchase retrieved successfully',
            'data' => new InventoryPurchaseResource($purchase),
        ]);
    }

    public function update(InventoryPurchaseRequest $request, int $id): JsonResponse
    {
        $purchase = $this->inventoryPurchaseService->update($id, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Inventory purchase updated successfully',
            'data' => new InventoryPurchaseResource($purchase),
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $this->inventoryPurchaseService->delete($id);

        return response()->json([
            'success' => true,
            'message' => 'Inventory purchase deleted successfully',
            'data' => null,
        ]);
    }
}
