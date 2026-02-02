<?php

namespace App\Observers;

use App\Models\Order;
use App\Enums\OrderStatus;

class OrderObserver
{
    public function updating(Order $order)
    {
        // Si intentan cambiar el estado del PADRE
        if ($order->isDirty('status') && $order->children()->exists()) {
            $newStatus = $order->status instanceof \BackedEnum ? $order->status->value : $order->status;
            
            // 1. Bloqueo: No puede pasar a estados finales si hay hijos sin armar
            $hijosPendientes = $order->children()
                ->whereNotIn('status', ['assembled', 'checked', 'dispatched', 'delivered', 'paid', 'cancelled'])
                ->exists();

            if ($hijosPendientes && in_array($newStatus, ['assembled', 'checked', 'dispatched'])) {
                throw new \Exception("No puedes avanzar el pedido padre porque tiene pedidos hijos pendientes de armado.");
            }

            // 2. Arrastre: Si el padre pasa a un estado logístico avanzado, los hijos lo siguen
            if (in_array($newStatus, ['dispatched', 'delivered', 'paid', 'cancelled'])) {
                $order->children()->update(['status' => $newStatus]);
            }
        }
    }
}