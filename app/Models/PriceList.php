<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PriceList extends Model
{
    protected $guarded = [];
    
    protected $casts = [
        'markup_percentage' => 'decimal:2',
    ];
}