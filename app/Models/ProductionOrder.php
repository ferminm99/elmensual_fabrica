<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class ProductionOrder extends Model
{
    use LogsActivity;
    protected $guarded = [];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll() // ¡Espía TODO!
            ->logOnlyDirty() // Solo guarda si hubo cambios reales
            ->dontSubmitEmptyLogs();
    }
    // Relación con la Tela
    public function rawMaterial(): BelongsTo
    {
        return $this->belongsTo(RawMaterial::class);
    }

    // Relación con el Artículo
    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    // Relación con los Talles que salieron
    public function items(): HasMany
    {
        return $this->hasMany(ProductionOrderItem::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

}