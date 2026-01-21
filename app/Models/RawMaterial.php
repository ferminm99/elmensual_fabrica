<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RawMaterial extends Model
{
    protected $guarded = [];

    protected $casts = [
        'stock_quantity' => 'decimal:3',
        'avg_cost' => 'decimal:2',
    ];

    // Relación con el Color
    public function color()
    {
        return $this->belongsTo(Color::class);
    }

    // Relación con el Proveedor (Supplier) - La dejamos lista para después
    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function stocks()
    {
        return $this->hasMany(RawMaterialStock::class);
    }

    public function suppliers()
    {
        return $this->belongsToMany(Supplier::class, 'material_supplier')
                    ->withPivot('price')
                    ->withTimestamps();
    }
    
}