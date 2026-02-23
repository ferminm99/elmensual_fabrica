<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Invoice; // Asegurate de tener el modelo Invoice
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;

class OrderPdfController extends Controller
{
    /**
     * Obtiene los items consolidados (Padre + Hijos)
     * Centralizamos esta lógica para que todos los PDFs vean lo mismo
     */
    private function getConsolidatedItems(Order $order)
    {
        $orderIds = Order::where('id', $order->id)
            ->orWhere('parent_id', $order->id)
            ->pluck('id')
            ->toArray();

        return OrderItem::with(['article', 'sku.color', 'sku.size'])
            ->whereIn('order_id', $orderIds)
            ->get()
            ->groupBy('sku_id');
    }

    /**
     * FACTURA / NOTA DE CRÉDITO AFIP
     * Ahora recibe el invoice_id opcional para soportar el historial
     */
    public function downloadInvoice(Order $order, $invoice_id = null)
    {
        if ($invoice_id) {
            $invoice = $order->invoices()->findOrFail($invoice_id);
        } else {
            $invoice = $order->invoices()->latest()->firstOrFail();
        }

        // Cargamos la vista de factura que ya tenías
        $pdf = Pdf::loadView('pdf.factura', [
            'order' => $order,
            'invoice' => $invoice
        ]);

        $filename = ($invoice->invoice_type === 'NC' ? 'NC_' : 'Factura_') . $invoice->number . '.pdf';
        
        return $pdf->stream($filename);
    }

    /**
     * REMITOS (Original 100% / Duplicado-Triplicado 50%)
     */
    public function remito(Order $order)
    {
        if ($order->billing_type === 'informal') {
            abort(403, 'Los pedidos 100% informales no llevan remito.');
        }

        $groupedItems = $this->getConsolidatedItems($order);
        $itemsParaRemito = [];
        
        foreach ($groupedItems as $skuId => $items) {
            $firstItem = $items->first();
            
            // Cantidad total real (100%)
            $qty100 = $items->sum(fn($i) => $i->packed_quantity > 0 ? $i->packed_quantity : $i->quantity);
            
            if ($qty100 <= 0) continue;

            // Cantidad al 50% para Duplicado/Triplicado si es mixto
            $qty50 = $order->billing_type === 'mixed' ? max(1, floor($qty100 / 2)) : $qty100;

            $itemsParaRemito[] = [
                'article' => $firstItem->article->name,
                'color'   => $firstItem->sku->color->name ?? '',
                'size'    => $firstItem->sku->size->name ?? '',
                'qty_100' => $qty100, 
                'qty_50'  => $qty50,  
            ];
        }

        $pdf = Pdf::loadView('pdf.remito', compact('order', 'itemsParaRemito'));
        return $pdf->stream('Remito_'.$order->id.'.pdf');
    }

    /**
     * PRESUPUESTO / BOLETA INTERNA (X)
     */
    public function presupuesto(Order $order)
    {
        if ($order->billing_type === 'fiscal') {
            abort(403, 'Los pedidos 100% fiscales no llevan presupuesto interno.');
        }

        $groupedItems = $this->getConsolidatedItems($order);
        $itemsParaPresupuesto = [];
        $totalPresupuesto = 0;

        foreach ($groupedItems as $skuId => $items) {
            $firstItem = $items->first();
            $qty = $items->sum(fn($i) => $i->packed_quantity > 0 ? $i->packed_quantity : $i->quantity);
            
            if ($qty <= 0) continue;

            // Si es mixto, el presupuesto es por el 50% restante
            if ($order->billing_type === 'mixed') {
                $qty = max(1, ceil($qty / 2)); 
            }

            $price = $items->max('unit_price');
            $subtotal = $qty * $price;
            $totalPresupuesto += $subtotal;

            $itemsParaPresupuesto[] = [
                'article'  => $firstItem->article->name,
                'color'    => $firstItem->sku->color->name ?? '',
                'size'     => $firstItem->sku->size->name ?? '',
                'qty'      => $qty,
                'price'    => $price,
                'subtotal' => $subtotal,
            ];
        }

        $pdf = Pdf::loadView('pdf.presupuesto', compact('order', 'itemsParaPresupuesto', 'totalPresupuesto'));
        return $pdf->stream('Boleta_Interna_'.$order->id.'.pdf');
    }

    /**
     * PICKING LIST (Para el armador)
     */
    public function picking(Order $order)
    {
        $groupedItems = $this->getConsolidatedItems($order);
        $pdf = Pdf::loadView('pdf.picking', compact('order', 'groupedItems'));
        return $pdf->stream('Picking_'.$order->id.'.pdf');
    }
}