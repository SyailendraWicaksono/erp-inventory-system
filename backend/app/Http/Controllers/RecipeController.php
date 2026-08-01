<?php

namespace App\Http\Controllers;

use App\Http\Requests\RecipeRequest;
use App\Http\Resources\RecipeResource;
use App\Services\RecipeService;
use Illuminate\Http\JsonResponse;

class RecipeController extends Controller
{
    public function __construct(private readonly RecipeService $recipeService)
    {
    }

    public function index(int $productId): JsonResponse
    {
        $recipes = $this->recipeService->getAll($productId);

        return response()->json([
            'success' => true,
            'message' => 'Recipes retrieved successfully',
            'data' => RecipeResource::collection($recipes),
        ]);
    }

    public function store(RecipeRequest $request, int $productId): JsonResponse
    {
        $recipe = $this->recipeService->create($productId, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Recipe created successfully',
            'data' => new RecipeResource($recipe),
        ], 201);
    }

    public function show(int $productId, int $recipeId): JsonResponse
    {
        $recipe = $this->recipeService->getById($productId, $recipeId);

        return response()->json([
            'success' => true,
            'message' => 'Recipe retrieved successfully',
            'data' => new RecipeResource($recipe),
        ]);
    }

    public function update(RecipeRequest $request, int $productId, int $recipeId): JsonResponse
    {
        $recipe = $this->recipeService->update($productId, $recipeId, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Recipe updated successfully',
            'data' => new RecipeResource($recipe),
        ]);
    }

    public function destroy(int $productId, int $recipeId): JsonResponse
    {
        $this->recipeService->delete($productId, $recipeId);

        return response()->json([
            'success' => true,
            'message' => 'Recipe deleted successfully',
            'data' => null,
        ]);
    }
}
