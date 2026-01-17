<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RawMaterial extends Model
{
    protected $guarded = [];

    protected $casts = [
        'stock_quantity' => 'decimal:3',
        'avg_cost' => 'decimal:2',
    ];
}