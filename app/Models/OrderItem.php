<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    protected $guarded = [];

    // Relación con la Orden Padre
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    // Relación con el ARTÍCULO (Esto arregla el "Artículo Desconocido")
    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    // Relación con el COLOR (Esto arregla el "Color Indefinido")
    public function color(): BelongsTo
    {
        return $this->belongsTo(Color::class);
    }

    // Relación con el SKU (Talles)
    public function sku(): BelongsTo
    {
        return $this->belongsTo(Sku::class);
    }
}