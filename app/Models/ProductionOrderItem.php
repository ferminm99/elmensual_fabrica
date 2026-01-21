<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductionOrderItem extends Model
{
    protected $guarded = [];
    
    // Relación inversa (Vuelve a la orden)
    public function productionOrder()
    {
        return $this->belongsTo(ProductionOrder::class);
    }
    
    // Relación con el SKU (Variante)
    public function sku()
    {
        return $this->belongsTo(Sku::class);
    }
    
}