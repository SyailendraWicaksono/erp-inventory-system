<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InventoryPurchase extends Model
{
    use HasFactory;

    protected $fillable = [
        'raw_material_id',
        'quantity',
        'purchase_date',
    ];

    public function rawMaterial()
    {
        return $this->belongsTo(RawMaterial::class);
    }
}