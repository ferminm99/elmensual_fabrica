<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\OrderPdfController;
use App\Http\Controllers\PriceListController;

Route::get('/', function () {
    return view('welcome');
});


// Rutas para pdfs
Route::get('/orders/{order}/pdf', [OrderPdfController::class, 'download'])->name('orders.pdf');
Route::get('/price-list/pdf', [PriceListController::class, 'download'])->name('price-list.pdf');