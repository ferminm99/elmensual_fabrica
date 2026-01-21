<?php

namespace App\Observers;

use App\Models\OrderItem;
use App\Models\Sku;

class OrderItemObserver
{
    // SE DISPARA AL CREAR UN ITEM (Descuenta Stock)
    public function created(OrderItem $item): void
    {
        if ($item->sku_id) {
            // decrement() resta aunque quede negativo, justo lo que pediste
            Sku::where('id', $item->sku_id)->decrement('stock_quantity', $item->quantity);
        }
    }

    // SE DISPARA AL ACTUALIZAR CANTIDAD (Ajusta la diferencia)
    public function updated(OrderItem $item): void
    {
        if ($item->sku_id && $item->isDirty('quantity')) {
            $difference = $item->quantity - $item->getOriginal('quantity');
            // Si aumenté la cantidad, difference es positivo -> Resto más stock
            // Si bajé la cantidad, difference es negativo -> Sumo stock (devuelvo)
            Sku::where('id', $item->sku_id)->decrement('stock_quantity', $difference);
        }
    }

    // SE DISPARA AL BORRAR UN ITEM (Devuelve Stock)
    public function deleted(OrderItem $item): void
    {
        if ($item->sku_id) {
            Sku::where('id', $item->sku_id)->increment('stock_quantity', $item->quantity);
        }
    }
}