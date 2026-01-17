<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Enums\ProductionStatus;

class ProductionOrder extends Model
{
    protected $guarded = [];

    protected $casts = [
        'status' => ProductionStatus::class,
    ];

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }
}