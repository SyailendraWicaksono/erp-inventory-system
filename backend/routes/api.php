<?php

use App\Http\Controllers\ProductController;
use App\Http\Controllers\RecipeController;
use Illuminate\Support\Facades\Route;

Route::apiResource('products', ProductController::class);

Route::get('products/{productId}/recipes', [RecipeController::class, 'index']);
Route::post('products/{productId}/recipes', [RecipeController::class, 'store']);
Route::get('products/{productId}/recipes/{recipeId}', [RecipeController::class, 'show']);
Route::put('products/{productId}/recipes/{recipeId}', [RecipeController::class, 'update']);
Route::delete('products/{productId}/recipes/{recipeId}', [RecipeController::class, 'destroy']);
