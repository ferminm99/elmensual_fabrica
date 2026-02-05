<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Traits\HasAccountingType;
use App\Enums\Origin;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;// use Spatie\Activitylog\Traits\LogsActivity; // Si usas logs, dejalo

class Order extends Model
{
    use HasAccountingType;
    use LogsActivity; 

    protected $fillable = [
        'client_id',
        'parent_id', // Fundamental para los Hijos (Splits)
        'locked_by',
        'locked_at',
        'order_date',
        'status',
        'priority', // Nuevo
        'billing_type',
        'billing_status', // Nuevo
        'total_amount',
        'amount_paid',
        'invoice_number',      // Nuevo
        'credit_note_number',  // Nuevo
        'invoiced_at',         // Nuevo
        'delivered_at',        // Nuevo
    ];

    protected $casts = [
        'locked_at' => 'datetime',
        'status' => OrderStatus::class,
        'origin' => \App\Enums\Origin::class,
        'order_date' => 'date',
        'invoiced_at' => 'datetime',
        'delivered_at' => 'datetime',
        'priority' => 'integer', // Para que Filament lo trate como número
    ];

    // --- RELACIONES CLAVE ---

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
    
    // RELACIONES RECURSIVAS (Padre e Hijos)
    // Esto es lo que permite la lógica de "Desglose" cuando editamos un pedido armado
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Order::class, 'parent_id');
    }

    // Relación para saber quién bloqueó
    public function lockedBy()
    {
        return $this->belongsTo(User::class, 'locked_by');
    }
    
    public function subOrders(): HasMany
    {
        return $this->hasMany(Order::class, 'parent_id');
    }
    
    // Helper visual para saber si es prioritario
    public function isPriority(): bool
    {
        return $this->priority >= 2;
    }

      public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    // Helper to calculate total
    public function getTotalAttribute()
    {
        return $this->items->sum(fn($item) => $item->quantity * $item->unit_price);
    }

}