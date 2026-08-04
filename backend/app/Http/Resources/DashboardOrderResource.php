<?php

namespace App\Http\Resources;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DashboardOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'total_today' => $this->resource['total_today'],
            'by_status' => $this->resource['by_status'],
            'active_orders' => $this->resource['active_orders']->map(fn (Order $order) => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'pickup_datetime' => $order->pickup_datetime,
                'order_status' => $order->order_status,
                'total_price' => (float) $order->total_price,
                'customer' => ['name' => $order->customer?->name],
            ])->all(),
        ];
    }
}
