<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\OrderPdfController;
use App\Http\Controllers\PriceListController;
use Barryvdh\DomPDF\Facade\Pdf;

Route::get('/', function () {
    return view('welcome');
});


// Rutas para pdfs
Route::get('/orders/{order}/pdf', [OrderPdfController::class, 'download'])->name('orders.pdf');
Route::get('/price-list/pdf', [PriceListController::class, 'download'])->name('price-list.pdf');
Route::get('/orders/{order}/invoice-download', function (Order $order) {
    $invoice = $order->invoice;
    $pdf = Pdf::loadView('pdf.invoice', ['order' => $order, 'invoice' => $invoice]);
    // SetPaper para que sea ligero
    $pdf->setPaper('a4', 'portrait')->setOption('isHtml5ParserEnabled', true);
    return $pdf->download("Factura-{$order->id}.pdf");
})->name('order.invoice.download')->middleware(['auth']);