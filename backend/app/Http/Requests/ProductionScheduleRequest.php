<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProductionScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'order_id' => ['required', 'exists:orders,id'],
            'start_time' => ['nullable', 'date'],
            'end_time' => ['nullable', 'date'],
        ];

        if ($this->isMethod('PUT') || $this->isMethod('PATCH')) {
            $rules['order_id'] = ['sometimes', 'exists:orders,id'];
            $rules['start_time'] = ['sometimes', 'date'];
            $rules['end_time'] = ['sometimes', 'date'];
        }

        return $rules;
    }
}
