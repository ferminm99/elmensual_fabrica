<style>
    body { font-family: sans-serif; font-size: 10px; color: #333; }
    .header { border-bottom: 2px solid #333; padding-bottom: 10px; margin-bottom: 20px; }
    .header h1 { margin: 0; font-size: 18px; }
    table { width: 100%; border-collapse: collapse; }
    th { background: #eee; text-align: left; padding: 5px; border: 1px solid #ccc; }
    td { padding: 5px; border: 1px solid #eee; }
    .total-row { font-size: 14px; font-weight: bold; text-align: right; margin-top: 20px; }
</style>

<div class="header">
    <h1>{{ strtoupper($invoice->type) }} - {{ $invoice->number }}</h1>
    <p><b>CLIENTE:</b> {{ $order->client->name }} | <b>FECHA:</b> {{ $invoice->created_at->format('d/m/Y') }}</p>
    <p><b>ZONA:</b> {{ $order->client->locality->zone->name ?? 'S/N' }}</p>
</div>

<table>
    <thead>
        <tr>
            <th>Código</th>
            <th>Artículo / Descripción</th>
            <th style="text-align: center;">Cantidad Total</th>
            <th style="text-align: right;">Precio Unit.</th>
            <th style="text-align: right;">Subtotal</th>
        </tr>
    </thead>
    <tbody>
        @php
            // Agrupamos los items por articulo para el PDF
            $itemsAgrupados = $order->items->where('packed_quantity', '>', 0)->groupBy('article_id');
        @endphp
        @foreach($itemsAgrupados as $articleId => $subItems)
            @php
                $first = $subItems->first();
                $cantidadTotal = $subItems->sum('packed_quantity');
            @endphp
            <tr>
                <td>{{ $first->article->code }}</td>
                <td>{{ $first->article->name }}</td>
                <td style="text-align: center;">{{ $cantidadTotal }}</td>
                <td style="text-align: right;">${{ number_format($first->unit_price, 2) }}</td>
                <td style="text-align: right;">${{ number_format($cantidadTotal * $first->unit_price, 2) }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

<div class="total-row">
    TOTAL FACTURADO: ${{ number_format($order->total_amount, 2) }}
</div>
<div style="margin-top: 10px; font-size: 8px; color: #666;">
    Observaciones: {{ $invoice->notes }}
</div>