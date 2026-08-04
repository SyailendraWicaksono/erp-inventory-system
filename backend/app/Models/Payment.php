<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;

    public const PAYMENT_STATUS_RECORDED = 'recorded';

    public const PAYMENT_STATUS_PAID = 'paid';

    protected $fillable = [
        'order_id',
        'payment_method',
        'payment_status',
        'payment_amount',
        'payment_date',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
