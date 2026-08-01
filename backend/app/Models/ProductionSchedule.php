<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductionSchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'start_time',
        'end_time',
        'production_status',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}