<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductionSchedule extends Model
{
    use HasFactory;

    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_FINISHED = 'finished';

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
