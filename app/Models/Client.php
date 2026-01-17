<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Client extends Model
{
    // Esto permite guardar cualquier dato sin errores de "Mass Assignment"
    protected $guarded = [];

    protected $casts = [
        'account_balance_fiscal' => 'decimal:2',
        'account_balance_internal' => 'decimal:2',
        
        // CORREGIDO: Usamos el nombre real de la base de datos
        'default_discount' => 'decimal:2', 
    ];

    public function priceList(): BelongsTo
    {
        return $this->belongsTo(PriceList::class);
    }

    public function referrals(): HasMany
    {
        return $this->hasMany(Client::class, 'referred_by_id');
    }

    public function referrer(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'referred_by_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}