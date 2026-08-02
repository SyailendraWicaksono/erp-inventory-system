<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InventoryPurchaseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'raw_material_id' => $this->raw_material_id,
            'quantity' => $this->quantity,
            'purchase_date' => $this->purchase_date,
            'raw_material' => [
                'id' => $this->rawMaterial->id,
                'name' => $this->rawMaterial->name,
                'unit' => $this->rawMaterial->unit,
            ],
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
