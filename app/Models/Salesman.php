<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Salesman extends Model
{
    protected $guarded = [];

    // Un viajante tiene muchos clientes
    public function clients(): HasMany
    {
        return $this->hasMany(Client::class);
    }

    // Lo que pediste: Las zonas que tiene asignadas (Muchos a Muchos)
    public function zones(): BelongsToMany
    {
        return $this->belongsToMany(Zone::class, 'salesman_zone');
    }
}