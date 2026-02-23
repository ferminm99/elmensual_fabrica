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
        $settings = \App\Models\Setting::first();
        $client = $order->client;

        // 1. Buscar factura previa para el CAE
        $invoice = $order->invoices()->latest()->first();
        
        // 2. Determinar la LETRA y TIPO
        if ($invoice && $invoice->cae_afip) {
            $letra = ($client->getAfipTaxConditionCode() === 1) ? 'A' : 'B';
            $tipoDoc = 'FACTURA';
            $esFiscal = true;
        } else {
            $letra = 'R';
            $tipoDoc = 'REMITO';
            $esFiscal = false;
        }

        $orderIds = Order::where('id', $order->id)->orWhere('parent_id', $order->id)->pluck('id')->toArray();
        $itemsAgrupados = OrderItem::with('article')->whereIn('order_id', $orderIds)->get()->groupBy('article_id');

        $itemsParaPdf = [];
        $subtotalBruto = 0;

        foreach ($itemsAgrupados as $articleId => $items) {
            $first = $items->first();
            $qty = $items->sum(fn($i) => $i->packed_quantity > 0 ? $i->packed_quantity : $i->quantity);
            if ($qty <= 0) continue;

            $qty_final = ($order->billing_type === 'mixed') ? max(1, floor($qty / 2)) : $qty;
            $price = $items->max('unit_price');
            $totalLinea = $qty_final * $price;
            $subtotalBruto += $totalLinea;

            $itemsParaPdf[] = [
                'code' => $first->article->code ?? 'S/C',
                'article' => $first->article->name,
                'qty' => $qty_final,
                'price' => $price,
                'total' => $totalLinea
            ];
        }

        // 3. Cálculos de Descuento e IVA
        $dtoPorc = $client->default_discount ?? 0;
        $dtoMonto = $subtotalBruto * ($dtoPorc / 100);
        $netoConDto = $subtotalBruto - $dtoMonto;
        $iva = $esFiscal ? ($netoConDto * 0.21) : 0;
        $totalFinal = $netoConDto + $iva;

        $totales = [
            'bruto' => $subtotalBruto,
            'dto_p' => $dtoPorc,
            'dto_m' => $dtoMonto,
            'neto' => $netoConDto,
            'iva' => $iva,
            'total' => $totalFinal
        ];

        return Pdf::loadView('pdf.remito', compact('order', 'itemsParaPdf', 'totales', 'settings', 'letra', 'tipoDoc', 'esFiscal', 'invoice'))->stream();
    }
    
    /**
     * PRESUPUESTO / BOLETA INTERNA (X)
     */
    public function presupuesto(Order $order)
    {
        if ($order->billing_type === 'fiscal') {
            abort(403, 'Los pedidos 100% fiscales no llevan presupuesto interno.');
        }

        $orderIds = Order::where('id', $order->id)->orWhere('parent_id', $order->id)->pluck('id')->toArray();
        
        // Agrupamos por ARTÍCULO (Ignoramos colores y talles como pediste)
        $itemsAgrupados = OrderItem::with('article')
            ->whereIn('order_id', $orderIds)
            ->get()
            ->groupBy('article_id');

        $itemsParaPresupuesto = [];
        $totalPresupuesto = 0;

        foreach ($itemsAgrupados as $articleId => $items) {
            $first = $items->first();
            $qty = $items->sum(fn($i) => $i->packed_quantity > 0 ? $i->packed_quantity : $i->quantity);
            
            if ($qty <= 0) continue;

            // Si es mixto, el presupuesto interno es por la MITAD de la cantidad
            if ($order->billing_type === 'mixed') {
                $qty = max(1, ceil($qty / 2)); 
            }

            $price = $items->max('unit_price');
            $subtotal = $qty * $price;
            $totalPresupuesto += $subtotal;

            $itemsParaPresupuesto[] = [
                'code'     => $first->article->code ?? 'S/C', // Agregamos el código
                'article'  => $first->article->name, // Solo el nombre del artículo
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