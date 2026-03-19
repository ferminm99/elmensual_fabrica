<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Invoice;
use Illuminate\Http\Request;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Barryvdh\DomPDF\Facade\Pdf;

class OrderPdfController extends Controller
{
    /**
     * FACTURA / NOTA DE CRÉDITO AFIP (Blanco)
     */
    public function downloadInvoice(Order $order, $invoice_id = null)
    {
        // Buscamos la factura específica (puede ser B o NC)
        $invoice = $invoice_id ? Invoice::findOrFail($invoice_id) : $order->invoices()->latest()->firstOrFail();
        
        $orderIds = Order::where('id', $order->id)->orWhere('parent_id', $order->id)->pluck('id')->toArray();
        
        // --- FILTRADO PARA NOTA DE CRÉDITO ---
        $query = OrderItem::with('article')->whereIn('order_id', $orderIds);
        
        if ($invoice->invoice_type === 'NC' && $invoice->parent_id) {
            // Buscamos la factura original que esta NC está anulando
            $facturaOriginal = Invoice::find($invoice->parent_id);
            if ($facturaOriginal) {
                // Solo cargamos artículos que existían ANTES o DURANTE la factura original
                // Esto evita que entren los artículos nuevos del "hijo" en la NC
                $query->where('created_at', '<=', $facturaOriginal->created_at);
            }
        }

        $itemsAgrupados = $query->get()->groupBy('article_id');
        $itemsParaPdf = [];

        foreach ($itemsAgrupados as $articleId => $items) {
            $first = $items->first();
            $qty = $items->sum(fn($i) => $i->packed_quantity > 0 ? $i->packed_quantity : $i->quantity);
            if ($qty <= 0) continue;

            // REGLA: Si el pedido es mixto, la Factura/NC siempre muestra el 50%
            if ($order->billing_type === 'mixed') {
                $qty = max(1, floor($qty / 2));
            }

            $price = $items->max('unit_price');
            $itemsParaPdf[] = [
                'code'    => $first->article->code ?? 'S/C',
                'article' => $first->article->name,
                'qty'     => $qty,
                'price'   => $price,
                'total'   => $qty * $price,
            ];
        }

        // --- QR AFIP (Basado en la factura actual) ---
        $qrImage = null;
        if ($invoice->cae_afip) {
            // Importante: No usar Condición frente al IVA del cliente si fue un DNI/Consumidor final.
            // El Invoice type ya tiene la letra correcta.
            $letra = str_replace('NC ', '', $invoice->invoice_type); // Por si guardaste 'NC B' en vez de 'NC'
            if ($letra === 'NC') $letra = 'B'; // Asumimos B por defecto si solo dice NC

            // 1=Factura A, 6=Factura B, 3=NC A, 8=NC B
            // Usamos directamente el tipo del Invoice si es posible, sino inferimos
            $esA = ($order->client && $order->client->getAfipTaxConditionCode() === 1);
            $tipoCmp = ($invoice->invoice_type === 'NC') ? ($esA ? 3 : 8) : ($esA ? 1 : 6);
            
            // Detección de documento
            $docTipo = 99; // Consumidor Final
            $docNro = 0;
            if ($order->client_id && $order->client->tax_id) {
                $cuitLimpio = preg_replace('/[^0-9]/', '', $order->client->tax_id);
                if (strlen($cuitLimpio) >= 8) {
                    $docNro = (int) $cuitLimpio;
                    $docTipo = strlen($cuitLimpio) > 8 ? 80 : 96; // 80=CUIT, 96=DNI
                }
            }

            // Datos exactos requeridos por AFIP
            $qrData = [
                "ver" => 1,
                "fecha" => $invoice->created_at->format('Y-m-d'),
                "cuit" => 30633784104, // Tu CUIT emisor
                "ptoVta" => 10,
                "tipoCmp" => $tipoCmp,
                "nroCmp" => (int) last(explode('-', $invoice->number)),
                "importe" => (float) abs($invoice->total_fiscal),
                "moneda" => "PES",
                "ctz" => 1,
                "tipoDocRec" => $docTipo,
                "nroDocRec" => $docNro,
                "tipoCodAut" => "E",
                "codAut" => (int) $invoice->cae_afip
            ];

            // 1. JSON Encode
            $jsonAfip = json_encode($qrData);
            // 2. Base64 Encode
            $base64Afip = base64_encode($jsonAfip);
            // 3. URL Final
            $urlAfip = "https://www.afip.gob.ar/fe/qr/?p=" . $base64Afip;

            // 4. Generamos el SVG localmente y lo pasamos a base64 para embeber en el DOMPDF
            // Requiere: use SimpleSoftwareIO\QrCode\Facades\QrCode; al principio del archivo
            $qrSvg = \SimpleSoftwareIO\QrCode\Facades\QrCode::format('svg')->size(130)->margin(0)->generate($urlAfip);
            $qrImage = base64_encode($qrSvg);
        }

        return Pdf::loadView('pdf.factura', compact('order', 'invoice', 'itemsParaPdf', 'qrImage'))->stream();
    }

    /**
     * REMITOS (Logística)
     */
    public function remito(Order $order)
    {
        $settings = \App\Models\Setting::first();
        $invoice = $order->invoices()->where('invoice_type', 'B')->latest()->first();
        $esFiscal = $invoice && $invoice->cae_afip;

        $letra = $esFiscal ? (($order->client->getAfipTaxConditionCode() === 1) ? 'A' : 'B') : 'R';
        $tipoDoc = $esFiscal ? 'FACTURA' : 'REMITO';

        $orderIds = Order::where('id', $order->id)->orWhere('parent_id', $order->id)->pluck('id')->toArray();
        $itemsAgrupados = OrderItem::with('article')->whereIn('order_id', $orderIds)->get()->groupBy('article_id');

        $itemsParaPdf = [];
        $subtotalBruto = 0;

        foreach ($itemsAgrupados as $articleId => $items) {
            $first = $items->first();
            $qty = $items->sum(fn($i) => $i->packed_quantity > 0 ? $i->packed_quantity : $i->quantity);
            if ($qty <= 0) continue;

            // En el remito fiscal de pedido mixto, mostramos el 50%
            if ($order->billing_type === 'mixed' && $esFiscal) {
                $qty = max(1, floor($qty / 2));
            }

            $price = $items->max('unit_price');
            $subtotalBruto += ($qty * $price);
            $itemsParaPdf[] = [
                'code'    => $first->article->code ?? 'S/C',
                'article' => $first->article->name,
                'qty'     => $qty,
                'price'   => $price,
                'total'   => $qty * $price
            ];
        }

        $dtoMonto = $subtotalBruto * (($order->client->default_discount ?? 0) / 100);
        $neto = $subtotalBruto - $dtoMonto;
        $iva = $esFiscal ? ($neto * 0.21) : 0;
        $totales = [
            'bruto' => $subtotalBruto, 'dto_p' => $order->client->default_discount, 
            'dto_m' => $dtoMonto, 'neto' => $neto, 'iva' => $iva, 'total' => $neto + $iva
        ];

        // QR para Remito
        $qrImage = null;
        if ($esFiscal) {
            $qrData = ["ver" => 1, "fecha" => $invoice->created_at->format('Y-m-d'), "cuit" => 30633784104, "ptoVta" => 10, "tipoCmp" => ($letra === 'A' ? 1 : 6), "nroCmp" => (int) last(explode('-', $invoice->number)), "importe" => (float) abs($invoice->total_fiscal), "moneda" => "PES", "ctz" => 1, "tipoDocRec" => ($letra === 'A' ? 80 : 99), "nroDocRec" => ($letra === 'A' ? (int) $order->client->tax_id : 0), "tipoCodAut" => "E", "codAut" => (int) $invoice->cae_afip];
            $qrImage = "https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=" . urlencode("https://www.afip.gob.ar/fe/qr/?p=" . base64_encode(json_encode($qrData)));
        }

        return Pdf::loadView('pdf.remito', compact('order', 'itemsParaPdf', 'totales', 'settings', 'letra', 'tipoDoc', 'esFiscal', 'invoice', 'qrImage'))->stream();
    }

    public function presupuesto(Order $order)
    {
        $orderIds = Order::where('id', $order->id)->orWhere('parent_id', $order->id)->pluck('id')->toArray();
        $itemsAgrupados = OrderItem::with('article')->whereIn('order_id', $orderIds)->get()->groupBy('article_id');

        $itemsParaPresupuesto = [];
        $totalPresupuesto = 0;

        foreach ($itemsAgrupados as $articleId => $items) {
            $first = $items->first();
            $qty = $items->sum(fn($i) => $i->packed_quantity > 0 ? $i->packed_quantity : $i->quantity);
            if ($qty <= 0) continue;

            $price = $items->max('unit_price');
            $subtotal = $qty * $price;
            $totalPresupuesto += $subtotal;

            $itemsParaPresupuesto[] = [
                'code'     => $first->article->code ?? 'S/C',
                'article'  => $first->article->name,
                'qty'      => $qty,
                'price'    => $price,
                'subtotal' => $subtotal,
            ];
        }

        return Pdf::loadView('pdf.presupuesto', compact('order', 'itemsParaPresupuesto', 'totalPresupuesto'))->stream();
    }

    public function picking(Order $order)
    {
        $orderIds = Order::where('id', $order->id)->orWhere('parent_id', $order->id)->pluck('id')->toArray();
        $groupedItems = OrderItem::with(['article', 'sku.color', 'sku.size'])
            ->whereIn('order_id', $orderIds)
            ->get()
            ->groupBy('sku_id');

        return Pdf::loadView('pdf.picking-list', compact('order', 'groupedItems'))->stream('Picking_'.$order->id.'.pdf');
    }
}