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

    protected $guarded = [];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    protected $casts = [
        'internal_debt' => 'decimal:2',
        'fiscal_debt' => 'decimal:2',
        'default_discount' => 'decimal:2', 
    ];

    public const AFIP_TAX_CONDITIONS = [
        1 => 'Responsable Inscripto',
        5 => 'Consumidor Final',
        6 => 'Exento',
        13 => 'Monotributista',
    ];


    // --- RELACIONES ---

    public function locality(): BelongsTo
    {
        return $this->belongsTo(Locality::class);
    }

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

    public function payments(): HasMany
    {
        return $this->hasMany(Transaction::class)->orderBy('created_at', 'desc');
    }

    public function salesman(): BelongsTo
    {
        return $this->belongsTo(Salesman::class, 'referred_by_id');
    }
    // 2. Modifica el método para que sea más dinámico
    public function getAfipTaxConditionCode(): int
    {
        // Si ya tienes el ID en la base de datos (después de la migración), úsalo
        if (!empty($this->afip_tax_condition_id)) {
            return (int) $this->afip_tax_condition_id;
        }

        // Si no, intentamos mapear el texto que ya tienes guardado
        $condition = strtoupper($this->tax_condition ?? '');
        
        return match (true) {
            str_contains($condition, 'INSCRIPTO') => 1,
            str_contains($condition, 'MONOTRIBUTO') => 13,
            str_contains($condition, 'EXENTO') => 6,
            default => 5, // Valor por defecto: Consumidor Final
        };
    }

    // --- LÓGICA DE NEGOCIO (FIFO) ---

    public function distributePayment(float $amount, string $originType)
    {
        $orders = $this->orders()
            ->where('status', OrderStatus::Delivered)
            ->where('billing_type', $originType === 'Fiscal' ? 'fiscal' : 'informal')
            ->orderBy('order_date', 'asc') // Usamos order_date que es lo más lógico
            ->get();

        $remainingPayment = $amount;

        foreach ($orders as $order) {
            if ($remainingPayment <= 0) break;

            $debtOnOrder = $order->total_amount - $order->amount_paid;

            if ($debtOnOrder <= 0) {
                $order->update(['status' => OrderStatus::Paid]);
                continue;
            }

            if ($remainingPayment >= $debtOnOrder) {
                $order->update([
                    'amount_paid' => $order->total_amount,
                    'status' => OrderStatus::Paid,
                ]);
                $remainingPayment -= $debtOnOrder;
            } else {
                $order->update([
                    'amount_paid' => $order->amount_paid + $remainingPayment,
                ]);
                $remainingPayment = 0;
            }
        }
    }
}