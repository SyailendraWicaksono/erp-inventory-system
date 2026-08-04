<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $outer = $this->isMethod('PUT') ? 'sometimes' : 'required';

        return [
            'order_id' => [$outer, 'integer', 'exists:orders,id'],
            'payment_method' => [$outer, 'string', 'max:255'],
            'payment_amount' => [$outer, 'numeric', 'gt:0'],
            'payment_date' => ['nullable', 'date'],
        ];
    }
}
