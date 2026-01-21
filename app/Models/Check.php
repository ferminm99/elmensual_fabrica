<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Enums\CheckStatus;

class Check extends Model
{
    // Usamos fillable para definir explícitamente qué campos se pueden guardar
    protected $fillable = [
        'client_id',
        'supplier_id', // <--- AGREGADO (Para cuando lo entregamos)
        'bank_name',
        'number',
        'owner',
        'amount',
        'payment_date',
        'status',
        'type',
        'is_echeq',
        'deposited_at',
        'delivered_at', // <--- AGREGADO (Fecha de entrega)
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'payment_date' => 'date',
        'is_echeq' => 'boolean',
        'deposited_at' => 'date',
        'delivered_at' => 'datetime', // <--- AGREGADO
        'status' => CheckStatus::class,
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    // Relación con el Proveedor (al que le damos el cheque)
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    // Esta era la vieja, la dejamos por compatibilidad o la podés borrar si usas 'supplier()'
    public function deliveredTo(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }
}