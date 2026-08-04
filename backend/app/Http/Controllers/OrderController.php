<?php

namespace App\Http\Controllers;

use App\Http\Requests\OrderRequest;
use App\Http\Resources\OrderResource;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;

class OrderController extends Controller
{
    public function __construct(private readonly OrderService $orderService) {}

    public function index(): JsonResponse
    {
        $orders = $this->orderService->getAll();

        return response()->json([
            'success' => true,
            'message' => 'Orders retrieved successfully',
            'data' => OrderResource::collection($orders),
        ]);
    }

    public function store(OrderRequest $request): JsonResponse
    {
        $order = $this->orderService->create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Order created successfully',
            'data' => new OrderResource($order),
        ], 201);
    }

    public function show(int $id): JsonResponse
    {
        $order = $this->orderService->getById($id);

        return response()->json([
            'success' => true,
            'message' => 'Order retrieved successfully',
            'data' => new OrderResource($order),
        ]);
    }

    public function update(OrderRequest $request, int $id): JsonResponse
    {
        $order = $this->orderService->update($id, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Order updated successfully',
            'data' => new OrderResource($order),
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $this->orderService->delete($id);

        return response()->json([
            'success' => true,
            'message' => 'Order deleted successfully',
            'data' => null,
        ]);
    }

    public function confirm(int $id): JsonResponse
    {
        $order = $this->orderService->confirm($id);

        return response()->json([
            'success' => true,
            'message' => 'Order confirmed successfully',
            'data' => new OrderResource($order),
        ]);
    }
}
