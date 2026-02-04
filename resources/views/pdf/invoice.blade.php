<style>
    body { font-family: sans-serif; font-size: 10px; color: #333; line-height: 1.4; }
    .header { border-bottom: 2px solid #000; padding-bottom: 5px; margin-bottom: 15px; }
    .header h1 { font-size: 16px; margin: 0; }
    table { width: 100%; border-collapse: collapse; }
    th { background: #eee; text-align: left; padding: 4px; border: 1px solid #999; font-weight: bold; }
    td { padding: 4px; border: 1px solid #ccc; }
    .total-section { margin-top: 20px; text-align: right; border-top: 1px solid #000; padding-top: 10px; }
    .total-amount { font-size: 14px; font-weight: bold; }
</style>

<div class="header">
    <h1>COMPROBANTE DE VENTA ({{ strtoupper($invoice->type) }})</h1>
    <p><b>Número:</b> {{ $invoice->number }} | <b>Fecha:</b> {{ $invoice->created_at->format('d/m/Y') }}</p>
    <p><b>Cliente:</b> {{ $order->client->name }} | <b>CUIT:</b> {{ $order->client->cuit ?? 'S/D' }}</p>
</div>

<table>
    <thead>
        <tr>
            <th>Código</th>
            <th>Descripción Artículo</th>
            <th style="text-align: center;">Cantidad Total</th>
            <th style="text-align: right;">P. Unitario</th>
            <th style="text-align: right;">Subtotal</th>
        </tr>
    </thead>
    <tbody>
        @php
            // Agrupamos items por artículo para el PDF
            $itemsAgrupados = $order->items->where('packed_quantity', '>', 0)->groupBy('article_id');
        @endphp
        @foreach($itemsAgrupados as $articleId => $items)
            @php
                $first = $items->first();
                $qty = $items->sum('packed_quantity');
            @endphp
            <tr>
                <td>{{ $first->article->code }}</td>
                <td>{{ $first->article->name }}</td>
                <td style="text-align: center;">{{ $qty }}</td>
                <td style="text-align: right;">${{ number_format($first->unit_price, 2) }}</td>
                <td style="text-align: right;">${{ number_format($qty * $first->unit_price, 2) }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

<div class="total-section">
    <span class="total-amount">TOTAL FINAL: ${{ number_format($order->total_amount, 2) }}</span>
    <p style="font-size: 8px;">Comprobante no válido como factura fiscal si el tipo es 'Informal'.</p>
</div>