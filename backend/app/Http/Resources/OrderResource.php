<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'pickup_datetime' => $this->pickup_datetime,
            'order_status' => $this->order_status,
            'total_price' => $this->total_price,
            'customer' => $this->customer ? [
                'id' => $this->customer->id,
                'name' => $this->customer->name,
                'phone_number' => $this->customer->phone_number,
            ] : null,
            'items' => $this->items->map(fn ($item) => [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'quantity' => $item->quantity,
                'customization_note' => $item->customization_note,
                'unit_price' => $item->unit_price,
                'subtotal' => $item->subtotal,
                'product' => $item->product ? [
                    'id' => $item->product->id,
                    'name' => $item->product->name,
                    'base_price' => $item->product->base_price,
                    'is_active' => $item->product->is_active,
                ] : null,
            ])->all(),
            'production_schedule' => $this->whenLoaded('productionSchedule', $this->productionSchedule ? [
                'id' => $this->productionSchedule->id,
                'production_status' => $this->productionSchedule->production_status,
                'start_time' => $this->productionSchedule->start_time,
                'end_time' => $this->productionSchedule->end_time,
            ] : null),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
