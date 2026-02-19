<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\OrderPdfController;
use App\Http\Controllers\PriceListController;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\Order;

Route::get('/', function () {
    return view('welcome');
});


// Rutas para pdfs
Route::get('/orders/{order}/pdf', [OrderPdfController::class, 'download'])->name('orders.pdf');
Route::get('/price-list/pdf', [PriceListController::class, 'download'])->name('price-list.pdf');
// Movimos la lógica al controlador para que no falle
Route::get('/orders/{order}/invoice-download', [OrderPdfController::class, 'downloadInvoice'])
    ->name('order.invoice.download')
    ->middleware(['auth']);