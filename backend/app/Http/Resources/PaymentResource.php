<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'payment_method' => $this->payment_method,
            'payment_status' => $this->payment_status,
            'payment_amount' => $this->payment_amount,
            'payment_date' => $this->payment_date,
            'order' => [
                'id' => $this->order->id,
                'order_number' => $this->order->order_number,
                'pickup_datetime' => $this->order->pickup_datetime,
                'order_status' => $this->order->order_status,
                'total_price' => $this->order->total_price,
            ],
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}