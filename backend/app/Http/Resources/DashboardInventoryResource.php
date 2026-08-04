<?php

namespace App\Http\Resources;

use App\Models\RawMaterial;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DashboardInventoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'total' => $this->resource['total'],
            'by_status' => $this->resource['by_status'],
            'at_risk' => $this->resource['at_risk']->map(fn (RawMaterial $material) => [
                'id' => $material->id,
                'name' => $material->name,
                'unit' => $material->unit,
                'stock_quantity' => (float) $material->stock_quantity,
                'status' => $material->status,
            ])->all(),
        ];
    }
}
