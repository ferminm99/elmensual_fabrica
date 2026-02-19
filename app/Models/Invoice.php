<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invoice extends Model
{
    protected $fillable = [
        'order_id',
        'cae_afip',
        'invoice_type',
        'total_fiscal',
        'parent_id',
        'number',
        'cae_expiry',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}