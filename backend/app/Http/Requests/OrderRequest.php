<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class OrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $outer = $this->isMethod('PUT') ? 'sometimes' : 'required';

        return [
            'customer_name' => [$outer, 'string', 'max:255'],
            'phone_number' => [$outer, 'string', 'max:255'],
            'pickup_datetime' => [$outer, 'date'],
            'items' => [$outer, 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.customization_note' => ['nullable', 'string'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
