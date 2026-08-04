<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InventoryAvailabilityController;
use App\Http\Controllers\InventoryPurchaseController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductionScheduleController;
use App\Http\Controllers\RawMaterialController;
use App\Http\Controllers\RecipeController;
use Illuminate\Support\Facades\Route;

Route::apiResource('products', ProductController::class);
Route::apiResource('raw-materials', RawMaterialController::class);
Route::apiResource('inventory-purchases', InventoryPurchaseController::class);

Route::get('inventory/availability', [InventoryAvailabilityController::class, 'index']);

Route::get('products/{productId}/recipes', [RecipeController::class, 'index']);
Route::post('products/{productId}/recipes', [RecipeController::class, 'store']);
Route::get('products/{productId}/recipes/{recipeId}', [RecipeController::class, 'show']);
Route::put('products/{productId}/recipes/{recipeId}', [RecipeController::class, 'update']);
Route::delete('products/{productId}/recipes/{recipeId}', [RecipeController::class, 'destroy']);

Route::apiResource('production-schedules', ProductionScheduleController::class);
Route::patch('production-schedules/{production_schedule}/start', [ProductionScheduleController::class, 'start']);
Route::patch('production-schedules/{production_schedule}/finish', [ProductionScheduleController::class, 'finish']);

Route::apiResource('orders', OrderController::class);
Route::patch('orders/{order}/confirm', [OrderController::class, 'confirm']);

Route::apiResource('payments', PaymentController::class);
Route::patch('payments/{payment}/verify', [PaymentController::class, 'verify']);

Route::get('dashboard', [DashboardController::class, 'index']);
Route::get('dashboard/orders', [DashboardController::class, 'orders']);
Route::get('dashboard/production', [DashboardController::class, 'production']);
Route::get('dashboard/inventory', [DashboardController::class, 'inventory']);
Route::get('dashboard/payments', [DashboardController::class, 'payments']);
