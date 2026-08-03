<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    public const ORDER_STATUS_CONFIRMED = 'confirmed';

    public const ORDER_STATUS_FINISHED = 'finished';

    protected $fillable = [
        'customer_id',
        'order_number',
        'pickup_datetime',
        'order_status',
        'total_price',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payment()
    {
        return $this->hasOne(Payment::class);
    }

    public function productionSchedule()
    {
        return $this->hasOne(ProductionSchedule::class);
    }
}
