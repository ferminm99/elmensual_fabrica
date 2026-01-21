<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Client extends Model
{
    use LogsActivity;
    // Esto permite guardar cualquier dato sin errores de "Mass Assignment"
    protected $guarded = [];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll() // ¡Espía TODO!
            ->logOnlyDirty() // Solo guarda si hubo cambios reales
            ->dontSubmitEmptyLogs();
    }

    protected $casts = [
        'internal_debt' => 'decimal:2',
        'fiscal_debt' => 'decimal:2',
        
        // CORREGIDO: Usamos el nombre real de la base de datos
        'default_discount' => 'decimal:2', 
    ];

    public function priceList(): BelongsTo
    {
        return $this->belongsTo(PriceList::class);
    }

    public function referrals(): HasMany
    {
        return $this->hasMany(Client::class, 'referred_by_id');
    }

    public function referrer(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'referred_by_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function payments()
    {
        // Buscamos transacciones donde este cliente sea el dueño
        return $this->hasMany(Transaction::class)->orderBy('created_at', 'desc');
    }

    public function distributePayment(float $amount, string $originType)
    {
        // 1. Buscamos pedidos ENTREGADOS (Deuda generada) de este cliente
        // Ordenados por fecha (Los más viejos primero)
        // Filtramos por tipo de facturación (Si pagan en negro, matamos deuda negra)
        
        $orders = $this->orders()
            ->where('status', OrderStatus::Delivered) // Solo los que están "A Cobrar"
            ->where('billing_type', $originType === 'Fiscal' ? 'fiscal' : 'informal') // Ajustá esto según tus valores de billing_type
            ->orderBy('date', 'asc') // FIFO: Primero los viejos
            ->get();

        $remainingPayment = $amount;

        foreach ($orders as $order) {
            if ($remainingPayment <= 0) break; // Se acabó la plata

            // Cuánto falta pagar de este pedido
            $debtOnOrder = $order->total_amount - $order->amount_paid;

            if ($debtOnOrder <= 0) {
                // Este pedido ya estaba cubierto (por si acaso), lo marcamos pagado y seguimos
                $order->update(['status' => OrderStatus::Paid]);
                continue;
            }

            if ($remainingPayment >= $debtOnOrder) {
                // A. EL PAGO CUBRE TODO EL PEDIDO
                $order->update([
                    'amount_paid' => $order->total_amount, // Llenamos el tanque
                    'status' => OrderStatus::Paid,        // ¡CERRADO!
                ]);
                
                $remainingPayment -= $debtOnOrder; // Restamos lo usado

            } else {
                // B. EL PAGO ES PARCIAL (No alcanza para cerrar el pedido)
                $order->update([
                    'amount_paid' => $order->amount_paid + $remainingPayment,
                    // NO cambiamos el estado, sigue "Entregado" (Debiendo)
                ]);
                
                $remainingPayment = 0; // Se gastó todo
            }
        }
    }
    
}