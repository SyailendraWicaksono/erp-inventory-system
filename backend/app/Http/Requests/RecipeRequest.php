<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RecipeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'recipe_name' => ['required', 'string', 'max:255'],
            'recipe_details' => ['required', 'array', 'min:1'],
            'recipe_details.*.raw_material_id' => ['required', 'integer', 'distinct', 'exists:raw_materials,id'],
            'recipe_details.*.quantity' => ['required', 'numeric', 'gt:0', 'max:9999999999.99'],
        ];
    }
}
