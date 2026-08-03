<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProductionScheduleRequest;
use App\Http\Resources\ProductionScheduleResource;
use App\Services\ProductionScheduleService;
use Illuminate\Http\JsonResponse;

class ProductionScheduleController extends Controller
{
    public function __construct(private readonly ProductionScheduleService $productionScheduleService) {}

    public function index(): JsonResponse
    {
        $schedules = $this->productionScheduleService->getAll();

        return response()->json([
            'success' => true,
            'message' => 'Production schedules retrieved successfully',
            'data' => ProductionScheduleResource::collection($schedules),
        ]);
    }

    public function store(ProductionScheduleRequest $request): JsonResponse
    {
        $schedule = $this->productionScheduleService->create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Production schedule created successfully',
            'data' => new ProductionScheduleResource($schedule),
        ], 201);
    }

    public function show(int $id): JsonResponse
    {
        $schedule = $this->productionScheduleService->getById($id);

        return response()->json([
            'success' => true,
            'message' => 'Production schedule retrieved successfully',
            'data' => new ProductionScheduleResource($schedule),
        ]);
    }

    public function update(ProductionScheduleRequest $request, int $id): JsonResponse
    {
        $schedule = $this->productionScheduleService->update($id, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Production schedule updated successfully',
            'data' => new ProductionScheduleResource($schedule),
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $this->productionScheduleService->delete($id);

        return response()->json([
            'success' => true,
            'message' => 'Production schedule deleted successfully',
            'data' => null,
        ]);
    }

    public function start(int $id): JsonResponse
    {
        $schedule = $this->productionScheduleService->start($id);

        return response()->json([
            'success' => true,
            'message' => 'Production started successfully',
            'data' => new ProductionScheduleResource($schedule),
        ]);
    }

    public function finish(int $id): JsonResponse
    {
        $schedule = $this->productionScheduleService->finish($id);

        return response()->json([
            'success' => true,
            'message' => 'Production finished successfully',
            'data' => new ProductionScheduleResource($schedule),
        ]);
    }
}