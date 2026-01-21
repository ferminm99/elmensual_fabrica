<?php

namespace App\Observers;

use App\Models\Payment;

class PaymentObserver
{
    // AL CREAR UN PAGO -> RESTAMOS DEUDA
    public function created(Payment $payment): void
    {
        $client = $payment->client;
        
        if ($payment->context === 'fiscal') {
            $client->decrement('fiscal_debt', $payment->amount);
        } else {
            $client->decrement('internal_debt', $payment->amount);
        }
    }

    // AL BORRAR UN PAGO -> DEVOLVEMOS LA DEUDA (Deshacer pago)
    public function deleted(Payment $payment): void
    {
        $client = $payment->client;
        
        if ($payment->context === 'fiscal') {
            $client->increment('fiscal_debt', $payment->amount);
        } else {
            $client->increment('internal_debt', $payment->amount);
        }
    }
    
    // Opcional: Si editás el monto, ajustamos la diferencia (avanzado)
    public function updated(Payment $payment): void 
    {
        if ($payment->isDirty('amount')) {
            $diff = $payment->amount - $payment->getOriginal('amount');
            // Si subió el pago, bajamos más deuda
            $column = $payment->context === 'fiscal' ? 'fiscal_debt' : 'internal_debt';
            $payment->client->decrement($column, $diff);
        }
    }
}