<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Recipe extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'recipe_name',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function recipeDetails()
    {
        return $this->hasMany(RecipeDetail::class);
    }
}
