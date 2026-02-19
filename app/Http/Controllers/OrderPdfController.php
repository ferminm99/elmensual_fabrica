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
    public function downloadInvoice(Order $order)
    {
        // Cargamos la relación invoice y los datos del cliente
        $order->load(['invoice', 'client', 'items.article']);

        // Verificamos que realmente tenga factura
        if (!$order->invoice) {
            abort(404, 'Este pedido no tiene una factura generada.');
        }

        // Usamos la vista específica para facturas
        $pdf = Pdf::loadView('pdf.invoice', [
            'order' => $order,
            'invoice' => $order->invoice
        ]);

        // Nombre de archivo profesional
        $filename = "factura-{$order->id}.pdf";

        return $pdf->stream($filename);
    }
}