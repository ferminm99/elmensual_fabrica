<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\HasAccountingType;
use App\Enums\Origin;
use App\Enums\TransactionType;

class Transaction extends Model
{
    use HasAccountingType;

    protected $guarded = [];

    protected $casts = [
        'amount' => 'decimal:2',
        'origin' => Origin::class,
        'type' => TransactionType::class,
        'payment_details' => 'array',
    ];

    // AGREGAMOS 'concept' AQUÍ PARA QUE NO REBOTE
    protected $fillable = [
        'company_account_id', 
        'type', 
        'amount', 
        'description', 
        'origin', 
        'payment_details',
        'concept' // <--- AGREGADO NUEVO
    ];
    
    public function account(): BelongsTo
    {
        return $this->belongsTo(CompanyAccount::class, 'company_account_id');
    }

    public function companyAccount(): BelongsTo
    {
        return $this->belongsTo(CompanyAccount::class);
    }
}