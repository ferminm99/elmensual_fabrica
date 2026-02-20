<?php

namespace App\Http\Controllers;

use App\Models\Order; // <--- ESTA LÍNEA ES LA QUE ARREGLA EL ERROR
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class OrderPdfController extends Controller
{
    /**
     * Descarga el comprobante interno de la Orden
     */
    public function download(Order $order)
    {
        // Cargamos las relaciones para la vista pdf.order
        $order->load(['client', 'items.sku.article', 'items.sku.size', 'items.sku.color']);

        $pdf = Pdf::loadView('pdf.order', compact('order'));

        return $pdf->stream("orden-{$order->id}.pdf");
    }

    /**
     * Descarga la Factura oficial (con CAE)
     */
    public function downloadInvoice(Order $order, Request $request)
    {
        $type = $request->query('type', 'B'); // Por defecto B

        $invoice = $order->invoices()
            ->where('invoice_type', $type)
            ->latest()
            ->first();

        if (!$invoice) {
            abort(404, "Comprobante no encontrado.");
        }

        $order->load(['client', 'items.article', 'items.sku.color', 'items.sku.size']);

        $pdf = Pdf::loadView('pdf.invoice', [
            'order' => $order, 
            'invoice' => $invoice,
            'isNC' => ($type === 'NC') // Helper para la vista
        ]);

        return $pdf->stream("{$type}-{$invoice->number}.pdf");
    }
}