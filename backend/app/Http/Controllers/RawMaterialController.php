<?php

namespace App\Http\Controllers;

use App\Http\Requests\RawMaterialRequest;
use App\Http\Resources\RawMaterialResource;
use App\Services\RawMaterialService;
use Illuminate\Http\JsonResponse;

class RawMaterialController extends Controller
{
    public function __construct(private readonly RawMaterialService $rawMaterialService) {}

    public function index(): JsonResponse
    {
        $rawMaterials = $this->rawMaterialService->getAll();

        return response()->json([
            'success' => true,
            'message' => 'Raw materials retrieved successfully',
            'data' => RawMaterialResource::collection($rawMaterials),
        ]);
    }

    public function store(RawMaterialRequest $request): JsonResponse
    {
        $rawMaterial = $this->rawMaterialService->create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Raw material created successfully',
            'data' => new RawMaterialResource($rawMaterial),
        ], 201);
    }

    public function show(int $id): JsonResponse
    {
        $rawMaterial = $this->rawMaterialService->getById($id);

        return response()->json([
            'success' => true,
            'message' => 'Raw material retrieved successfully',
            'data' => new RawMaterialResource($rawMaterial),
        ]);
    }

    public function update(RawMaterialRequest $request, int $id): JsonResponse
    {
        $rawMaterial = $this->rawMaterialService->update($id, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Raw material updated successfully',
            'data' => new RawMaterialResource($rawMaterial),
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $this->rawMaterialService->delete($id);

        return response()->json([
            'success' => true,
            'message' => 'Raw material deleted successfully',
            'data' => null,
        ]);
    }
}
