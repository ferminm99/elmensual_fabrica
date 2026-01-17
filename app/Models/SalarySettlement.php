<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalarySettlement extends Model
{
    protected $guarded = [];

    protected $casts = [
        'gross_amount' => 'decimal:2',
        'net_amount' => 'decimal:2',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(SalaryPeriod::class);
    }
}