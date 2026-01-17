<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Traits\HasAccountingType;
use App\Enums\Origin;

class Order extends Model
{
    use HasAccountingType;

    protected $guarded = [];

    protected $casts = [
        'origin' => Origin::class,
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class);
    }
    
    // Helper to calculate total
    public function getTotalAttribute()
    {
        return $this->items->sum(fn($item) => $item->quantity * $item->unit_price);
    }
}