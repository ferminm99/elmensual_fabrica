<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\OrderPdfController;
use App\Http\Controllers\PriceListController;
// (Ya no hace falta importar Pdf ni Order acá porque lo manejan los controladores)

Route::get('/', function () {
    return view('welcome');
});

// Rutas para pdfs (Las que ya tenías públicas o fuera del admin)
Route::get('/orders/{order}/pdf', [OrderPdfController::class, 'download'])->name('orders.pdf');
Route::get('/price-list/pdf', [PriceListController::class, 'download'])->name('price-list.pdf');

// Rutas protegidas (Requieren que el usuario esté logueado en el sistema)
Route::middleware(['auth'])->group(function () {
    
    // La que ya tenías para descargar las facturas/NC de AFIP
    Route::get('/orders/{order}/invoice-download/{invoice_id?}', [OrderPdfController::class, 'downloadInvoice'])
        ->name('order.invoice.download');
        
    // ==========================================
    // NUEVAS RUTAS FASE 6: HUB DE IMPRESIÓN
    // ==========================================
    
    // 1. Remitos (Triplicado)
    Route::get('/admin/orders/{order}/remito', [OrderPdfController::class, 'remito'])
        ->name('order.remito');
        
    // 2. Boleta / Presupuesto Interno
    Route::get('/admin/orders/{order}/presupuesto', [OrderPdfController::class, 'presupuesto'])
        ->name('order.presupuesto');
        
    // 3. Picking List (Para que el armador sepa qué buscar)
    Route::get('/admin/orders/{order}/pdf', [OrderPdfController::class, 'picking'])
        ->name('order.picking');
});