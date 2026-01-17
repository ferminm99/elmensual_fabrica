<?php

namespace App\Http\Controllers;

use App\Models\Category; // Asegúrate de tener el modelo Category
use Barryvdh\DomPDF\Facade\Pdf;

class PriceListController extends Controller
{
    public function download()
    {
        // Traemos categorías que tengan artículos, y sus artículos ordenados
        $categories = Category::with(['articles' => function($q) {
            $q->orderBy('code');
        }])->has('articles')->get();

        $pdf = Pdf::loadView('pdf.price-list', compact('categories'));
        return $pdf->stream('lista-precios.pdf');
    }
}