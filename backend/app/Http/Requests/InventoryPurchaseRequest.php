<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class InventoryPurchaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'raw_material_id' => ['required', 'exists:raw_materials,id'],
            'quantity' => ['required', 'numeric', 'min:0.01', 'max:9999999999.99'],
            'purchase_date' => ['nullable', 'date'],
        ];

        if ($this->isMethod('PUT') || $this->isMethod('PATCH')) {
            $rules['raw_material_id'] = ['sometimes', 'exists:raw_materials,id'];
            $rules['quantity'] = ['sometimes', 'numeric', 'min:0.01', 'max:9999999999.99'];
            $rules['purchase_date'] = ['sometimes', 'date'];
        }

        return $rules;
    }
}
