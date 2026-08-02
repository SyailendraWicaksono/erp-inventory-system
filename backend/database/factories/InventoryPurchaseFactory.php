<?php

namespace Database\Factories;

use App\Models\RawMaterial;
use Illuminate\Database\Eloquent\Factories\Factory;

class InventoryPurchaseFactory extends Factory
{
    public function definition(): array
    {
        return [
            'raw_material_id' => RawMaterial::factory(),
            'quantity' => 50,
            'purchase_date' => now(),
        ];
    }
}
