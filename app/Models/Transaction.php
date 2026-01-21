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

    // Definimos todo en fillable para evitar errores de asignación masiva
    protected $fillable = [
        'company_account_id', 
        'client_id',
        'supplier_id', // <--- AGREGADO (Para pagos a proveedores)
        'type', 
        'amount', 
        'description', 
        'origin', 
        'payment_details',
        'concept' 
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'origin' => Origin::class,
        'type' => TransactionType::class,
        'payment_details' => 'array',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(CompanyAccount::class, 'company_account_id');
    }

    // Alias para Filament que a veces busca companyAccount
    public function companyAccount(): BelongsTo
    {
        return $this->belongsTo(CompanyAccount::class, 'company_account_id');
    }
    
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    // Relación con Proveedor (Nueva)
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}