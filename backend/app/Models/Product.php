<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'base_price',
        'is_active',
    ];

    public function customizations()
    {
        return $this->hasMany(ProductCustomization::class);
    }

    public function recipes()
    {
        return $this->hasMany(Recipe::class);
    }
}
