<?php

namespace App\Http\Resources;

use App\Models\ProductionSchedule;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DashboardProductionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'total_today' => $this->resource['total_today'],
            'by_status' => $this->resource['by_status'],
            'active_schedules' => $this->resource['active_schedules']->map(fn (ProductionSchedule $schedule) => [
                'id' => $schedule->id,
                'order_number' => $schedule->order?->order_number,
                'start_time' => $schedule->start_time,
                'end_time' => $schedule->end_time,
                'production_status' => $schedule->production_status,
                'pickup_datetime' => $schedule->order?->pickup_datetime,
            ])->all(),
        ];
    }
}
