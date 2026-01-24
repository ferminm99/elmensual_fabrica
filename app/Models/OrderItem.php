<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    // Usamos solo guarded vacío para permitir cargar todo (más rápido para desarrollo interno)
    protected $guarded = [];

    // Relación con la Orden Padre
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    // Relación con el ARTÍCULO
    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    // Relación con el COLOR
    public function color(): BelongsTo
    {
        return $this->belongsTo(Color::class);
    }

    // Relación con el TALLE (Esto es lo que usa la tabla del Armador)
    public function size(): BelongsTo
    {
        return $this->belongsTo(Size::class);
    }

    // Relación con el SKU (Código técnico único)
    public function sku(): BelongsTo
    {
        return $this->belongsTo(Sku::class);
    }
}