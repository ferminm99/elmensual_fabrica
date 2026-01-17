<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class OrderPdfController extends Controller
{
    public function download(Order $order)
    {
        // Cargamos las relaciones para no tener errores en la vista
        $order->load(['client', 'items.sku.article', 'items.sku.size', 'items.sku.color']);

        $pdf = Pdf::loadView('pdf.order', compact('order'));

        // stream() abre el PDF en el navegador en lugar de bajarlo directo
        return $pdf->stream("orden-{$order->id}.pdf");
    }
}