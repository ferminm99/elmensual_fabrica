<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Enums\CheckStatus;

class Check extends Model
{
    protected $guarded = [];

    protected $casts = [
        'amount' => 'decimal:2',
        'payment_date' => 'date',
        'status' => CheckStatus::class,
    ];

    public function receivedFrom(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'received_from_client_id');
    }

    public function deliveredTo(): BelongsTo
    {
        // Polymorphic could be used here, but schema used specific nullable FKs
        return $this->belongsTo(Supplier::class, 'delivered_to_supplier_id');
    }
}