<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invoice extends Model
{
    protected $fillable = [
        'order_id',
        'cae_afip',      // Antes era 'cae'
        'invoice_type',  // Antes era 'type' (Enum: A, B, C, NC, ND)
        'total_fiscal',  // Antes era 'total_amount'
        'parent_id'      // Para notas de crédito
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}