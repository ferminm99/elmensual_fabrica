<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: 'Helvetica', sans-serif; font-size: 11px; color: #111; }
        .invoice-box { border: 1px solid #000; padding: 10px; }
        .type-box { position: absolute; left: 47%; top: 0; width: 45px; height: 40px; border: 1px solid #000; background: #fff; text-align: center; font-size: 30px; font-weight: bold; z-index: 10; }
        .header { width: 100%; border-bottom: 1px solid #000; margin-bottom: 10px; }
        .col { width: 50%; vertical-align: top; }
        .items-table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        .items-table th { background: #eee; border: 1px solid #000; padding: 5px; text-transform: uppercase; }
        .items-table td { border: 1px solid #ccc; padding: 5px; }
        .total-row { text-align: right; font-size: 16px; font-weight: bold; margin-top: 10px; }
        .cae-data { margin-top: 20px; font-size: 10px; text-align: right; border-top: 1px solid #eee; padding-top: 5px; }
    </style>
</head>
<body>
    <div class="invoice-box">
        <div class="type-box">B</div>
        
        <table class="header">
            <tr>
                <td class="col">
                    <h1 style="margin:0; color: #000;">EL MENSUAL</h1>
                    <p><strong>Razón Social:</strong> LAMOTEX.<br>
                    <strong>Domicilio:</strong> Saladillo, Buenos Aires<br>
                    <strong>Condición IVA:</strong> Responsable Inscripto</p>
                </td>
                <td class="col" style="text-align: right; border-left: 1px solid #000; padding-left: 15px;">
                    <h2 style="margin:0;">{{ $invoice->invoice_type === 'NC' ? 'NOTA DE CRÉDITO' : 'FACTURA' }}</h2>
                    <p><strong>Nro:</strong> {{ $invoice->number }}<br>
                    <strong>Fecha:</strong> {{ $invoice->created_at->format('d/m/Y') }}<br>
                    <strong>CUIT:</strong> 30633784104<br>
                    <strong>Ing. Brutos:</strong> 30-63378410-4<br>
                    <strong>Inicio Actividades:</strong> 01/01/2024</p>
                </td>
            </tr>
        </table>

        <div style="margin-bottom: 15px; padding: 5px; border: 1px solid #eee;">
            <strong>CLIENTE:</strong> {{ $order->client->name }}<br>
            <strong>CUIT/DNI:</strong> {{ $order->client->tax_id ?? 'Sin CUIT' }}<br>
            <strong>IVA:</strong> {{ $order->client->tax_condition ?? 'Consumidor Final' }}
        </div>

        <table class="items-table">
            <thead>
                <tr>
                    <th>Descripción</th>
                    <th>Cant.</th>
                    <th>Precio Unit.</th>
                    <th>Subtotal</th>
                </tr>
            </thead>
            <tbody>
                @php
                    // MAGIA: Obtenemos items del padre y de todos sus hijos
                    $orderIds = \App\Models\Order::where('id', $order->id)->orWhere('parent_id', $order->id)->pluck('id')->toArray();
                    $itemsAgrupados = \App\Models\OrderItem::with(['article', 'sku.color', 'sku.size'])
                                        ->whereIn('order_id', $orderIds)
                                        ->get();
                                        
                    // Agrupamos por SKU para mostrar cada talle/color por separado en la factura
                    $groupedBySku = $itemsAgrupados->groupBy('sku_id');
                @endphp

                @foreach($groupedBySku as $skuId => $items)
                    @php
                        // Obtenemos el primer item de este grupo para sacar los datos del artículo
                        $firstItem = $items->first();
                        
                        // Sumamos la cantidad armada (si es 0, usamos la pedida original)
                        $qty = $items->sum(function($i) {
                            return $i->packed_quantity > 0 ? $i->packed_quantity : $i->quantity;
                        });
                        
                        // Si es factura mixta (50%), dividimos la cantidad impresa por la mitad
                        // Solo para el PDF de la factura fiscal
                        if ($order->billing_type === 'mixed') {
                            $qty = max(1, floor($qty / 2)); // max(1) evita que salgan ceros si pidió 1 sola unidad
                        }

                        $price = $items->max('unit_price');
                        $subtotal = $qty * $price;
                        
                        if ($qty <= 0) continue;
                    @endphp
                    <tr>
                        <td>
                            {{ $firstItem->article->name }} 
                            ({{ $firstItem->sku->color->name ?? '' }} - {{ $firstItem->sku->size->name ?? '' }})
                        </td>
                        <td style="text-align:center;">{{ $qty }}</td>
                        <td style="text-align:right;">$ {{ number_format($price, 2, ',', '.') }}</td>
                        <td style="text-align:right;">$ {{ number_format($subtotal, 2, ',', '.') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="total-row">
            TOTAL {{ $invoice->invoice_type === 'NC' ? 'CRÉDITO' : 'FINAL' }}: $ {{ number_format(abs($invoice->total_fiscal), 2, ',', '.') }}
        </div>

        <div class="cae-data">
            <strong>CAE N°:</strong> {{ $invoice->cae_afip }}<br>
            <strong>Vencimiento CAE:</strong> {{ \Carbon\Carbon::parse($invoice->cae_expiry)->format('d/m/Y') }}
        </div>
    </div>
</body>
</html>