<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RawMaterialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'stock_quantity' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
            'unit' => ['required', 'string', 'max:255'],
            'expiration_date' => ['nullable', 'date'],
        ];

        if ($this->isMethod('PUT') || $this->isMethod('PATCH')) {
            $rules['name'] = ['sometimes', 'string', 'max:255'];
            $rules['stock_quantity'] = ['sometimes', 'numeric', 'min:0', 'max:9999999999.99'];
            $rules['unit'] = ['sometimes', 'string', 'max:255'];
        }

        return $rules;
    }
}
