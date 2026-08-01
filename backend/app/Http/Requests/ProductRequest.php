<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'base_price' => ['required', 'numeric', 'gt:0', 'max:9999999999.99'],
            'is_active' => ['nullable', 'boolean'],
        ];

        if ($this->isMethod('PUT') || $this->isMethod('PATCH')) {
            $rules['name'] = ['sometimes', 'string', 'max:255'];
            $rules['base_price'] = ['sometimes', 'numeric', 'gt:0', 'max:9999999999.99'];
        }

        return $rules;
    }
}
