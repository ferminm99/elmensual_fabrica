<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Sku extends Model
{
    // ESTA LÍNEA ES LA SOLUCIÓN:
    // Permite guardar cualquier columna (size_id, color_id, hex_code, etc.)
    protected $guarded = []; 

    // Relación con el Padre
    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    // Relación con Talle
    public function size(): BelongsTo
    {
        return $this->belongsTo(Size::class);
    }

    // Relación con Color
    public function color(): BelongsTo
    {
        return $this->belongsTo(Color::class);
    }
}