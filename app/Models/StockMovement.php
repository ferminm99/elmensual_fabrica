<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    protected $guarded = [];

    // Relación con el SKU (Producto específico)
    public function sku(): BelongsTo
    {
        return $this->belongsTo(Sku::class);
    }

    // Relación con el Usuario (Para saber quién fue)
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}