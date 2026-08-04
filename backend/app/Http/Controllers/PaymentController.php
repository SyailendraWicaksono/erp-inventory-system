<?php

namespace App\Http\Controllers;

use App\Http\Requests\PaymentRequest;
use App\Http\Resources\PaymentResource;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;

class PaymentController extends Controller
{
    public function __construct(private readonly PaymentService $paymentService) {}

    public function index(): JsonResponse
    {
        $payments = $this->paymentService->getAll();

        return response()->json([
            'success' => true,
            'message' => 'Payments retrieved successfully',
            'data' => PaymentResource::collection($payments),
        ]);
    }

    public function store(PaymentRequest $request): JsonResponse
    {
        $payment = $this->paymentService->create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Payment created successfully',
            'data' => new PaymentResource($payment),
        ], 201);
    }

    public function show(int $id): JsonResponse
    {
        $payment = $this->paymentService->getById($id);

        return response()->json([
            'success' => true,
            'message' => 'Payment retrieved successfully',
            'data' => new PaymentResource($payment),
        ]);
    }

    public function update(PaymentRequest $request, int $id): JsonResponse
    {
        $payment = $this->paymentService->update($id, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Payment updated successfully',
            'data' => new PaymentResource($payment),
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $this->paymentService->delete($id);

        return response()->json([
            'success' => true,
            'message' => 'Payment deleted successfully',
            'data' => null,
        ]);
    }

    public function verify(int $id): JsonResponse
    {
        $payment = $this->paymentService->verify($id);

        return response()->json([
            'success' => true,
            'message' => 'Payment verified successfully',
            'data' => new PaymentResource($payment),
        ]);
    }
}
