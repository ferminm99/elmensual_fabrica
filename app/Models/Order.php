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
use Spatie\Activitylog\LogOptions;

class Order extends Model
{
    use HasAccountingType;
    use LogsActivity;

    protected $fillable = [
        'client_id',
        'parent_id',    // Para vincular con el pedido original
        'invoice_id', 
        'total_amount',
        'amount_paid', 
        'status',
        'billing_type', 
        'order_date',
        'observations',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
    
    protected $casts = [
        'origin' => Origin::class,
        'status' => OrderStatus::class, 
        'order_date' => 'date',
    ];

    // --- RELACIONES ---

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

    // Relaciones para Pedidos Vinculados
    public function parentOrder(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'parent_id');
    }

    public function subOrders(): HasMany
    {
        return $this->hasMany(Order::class, 'parent_id');
    }
    
    // Helper to calculate total
    public function getTotalAttribute()
    {
        return $this->items->sum(fn($item) => $item->quantity * $item->unit_price);
    }
}