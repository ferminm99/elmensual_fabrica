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
        'invoice_id', // Si usas facturas
        'total_amount',
        'amount_paid', // <--- EL NUEVO QUE NECESITAMOS
        'status',
        'billing_type', // fiscal / informal
        'order_date',
        'observations',
        // Agregá cualquier otro campo que tengas en tu tabla orders
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll() // ¡Espía TODO!
            ->logOnlyDirty() // Solo guarda si hubo cambios reales
            ->dontSubmitEmptyLogs();
    }
    
    protected $casts = [
        'origin' => Origin::class,
        'status' => OrderStatus::class, 
        'order_date' => 'date',
    ];

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
    
    // Helper to calculate total
    public function getTotalAttribute()
    {
        return $this->items->sum(fn($item) => $item->quantity * $item->unit_price);
    }
}