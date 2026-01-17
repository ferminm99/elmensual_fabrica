<?php

namespace App\Observers;

use App\Models\StockMovement;

class StockMovementObserver
{
    /**
     * Handle the StockMovement "created" event.
     */
    public function created(StockMovement $stockMovement): void
    {
        $sku = $stockMovement->sku;

        if ($stockMovement->type === 'Entry') {
            // Si es Entrada (Devolución, Hallazgo, Ajuste positivo), SUMAMOS
            $sku->increment('stock_quantity', $stockMovement->quantity);
        } else {
            // Si es Salida (Robo, Pérdida, Regalo, Ajuste negativo), RESTAMOS
            $sku->decrement('stock_quantity', $stockMovement->quantity);
        }
    }

    /**
     * Handle the StockMovement "updated" event.
     */
    public function updated(StockMovement $stockMovement): void
    {
        //
    }

    /**
     * Handle the StockMovement "deleted" event.
     */
    public function deleted(StockMovement $stockMovement): void
    {
        //
    }

    /**
     * Handle the StockMovement "restored" event.
     */
    public function restored(StockMovement $stockMovement): void
    {
        //
    }

    /**
     * Handle the StockMovement "force deleted" event.
     */
    public function forceDeleted(StockMovement $stockMovement): void
    {
        //
    }
}
