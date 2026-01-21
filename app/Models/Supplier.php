<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends Model
{
    use LogsActivity;
    
    // Permitimos que se llenen estos campos
    protected $fillable = [
        'name', 'cuit', 'phone', 'email', 'address', 
        'fiscal_debt',   // <--- NOMBRE CORREGIDO
        'internal_debt'  // <--- NOMBRE CORREGIDO
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logAll()->logOnlyDirty()->dontSubmitEmptyLogs();
    }
    
    // Relación con Materias Primas
    public function rawMaterials(): BelongsToMany
    {
        return $this->belongsToMany(RawMaterial::class, 'material_supplier')
            ->withPivot('price')
            ->withTimestamps();
    }

    // --- NUEVAS RELACIONES PARA PAGOS ---

    // Cheques que le entregamos a este proveedor
    public function checks(): HasMany
    {
        return $this->hasMany(Check::class);
    }

    // Pagos en efectivo/transf que le hicimos
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }
    
    protected $casts = [
        'fiscal_debt' => 'decimal:2',   // <--- CORREGIDO
        'internal_debt' => 'decimal:2', // <--- CORREGIDO
    ];
}