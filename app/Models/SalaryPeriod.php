<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalaryPeriod extends Model
{
    protected $guarded = [];

    // Helper para mostrar "Enero 2026" en lugar de ids
    public function getNameAttribute(): string
    {
        return $this->month . '/' . $this->year;
    }

    public function settlements(): HasMany
    {
        return $this->hasMany(SalarySettlement::class, 'period_id');
    }
}