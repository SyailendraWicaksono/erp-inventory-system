<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class RawMaterial extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'stock_quantity',
        'unit',
        'expiration_date',
    ];

    public function recipeDetails()
    {
        return $this->hasMany(RecipeDetail::class);
    }

    public function inventoryPurchases()
    {
        return $this->hasMany(InventoryPurchase::class);
    }
}