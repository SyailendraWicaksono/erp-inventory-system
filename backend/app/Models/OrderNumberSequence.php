<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderNumberSequence extends Model
{
    protected $fillable = [
        'sequence_date',
        'last_value',
    ];
}
