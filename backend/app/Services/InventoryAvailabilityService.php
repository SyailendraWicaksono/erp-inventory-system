<?php

namespace App\Services;

use App\Models\RawMaterial;
use Illuminate\Database\Eloquent\Collection;

class InventoryAvailabilityService
{
    private const LOW_STOCK_THRESHOLD = 10;

    public function getStatus(): Collection
    {
        return RawMaterial::query()
            ->orderBy('name')
            ->get()
            ->each(function (RawMaterial $rawMaterial): void {
                $rawMaterial->status = $this->classify((float) $rawMaterial->stock_quantity);
            });
    }

    private function classify(float $stock): string
    {
        if ($stock <= 0) {
            return 'out_of_stock';
        }

        return $stock < self::LOW_STOCK_THRESHOLD ? 'low' : 'available';
    }
}
