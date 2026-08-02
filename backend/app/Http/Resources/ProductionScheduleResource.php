<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductionScheduleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'production_status' => $this->production_status,
            'order' => [
                'id' => $this->order->id,
                'order_number' => $this->order->order_number,
                'pickup_datetime' => $this->order->pickup_datetime,
                'order_status' => $this->order->order_status,
            ],
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
