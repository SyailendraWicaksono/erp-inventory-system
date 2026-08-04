<?php

namespace App\Http\Resources;

use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DashboardPaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'paid_total_today' => $this->resource['paid_total_today'],
            'outstanding_total' => $this->resource['outstanding_total'],
            'by_status' => $this->resource['by_status'],
            'recorded_today' => $this->resource['recorded_today']->map(fn (Payment $payment) => [
                'id' => $payment->id,
                'order_number' => $payment->order?->order_number,
                'payment_method' => $payment->payment_method,
                'payment_amount' => (float) $payment->payment_amount,
                'payment_date' => $payment->payment_date,
            ])->all(),
        ];
    }
}
