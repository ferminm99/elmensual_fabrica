<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    protected $guarded = [];

    protected $casts = [
        'account_balance_fiscal' => 'decimal:2',
        'account_balance_internal' => 'decimal:2',
    ];
}