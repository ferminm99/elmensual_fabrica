<style>
    body { font-family: sans-serif; font-size: 10px; color: #333; }
    .header { border-bottom: 1px solid #eee; padding-bottom: 10px; }
    table { width: 100%; border-collapse: collapse; margin-top: 20px; }
    th { background: #f9f9f9; text-align: left; }
    td, th { padding: 5px; border-bottom: 1px solid #eee; }
    .total { text-align: right; font-weight: bold; font-size: 14px; }
</style>

<div class="header">
    <h1>FACTURA {{ $invoice->number }}</h1>
    <p>Cliente: {{ $order->client->name }} | Fecha: {{ now()->format('d/m/Y') }}</p>
</div>

<table>
    <thead>
        <tr>
            <th>Articulo</th>
            <th>Color/Talle</th>
            <th>Cant.</th>
            <th>Precio</th>
            <th>Subtotal</th>
        </tr>
    </thead>
    <tbody>
        @foreach($order->items as $item)
            <tr>
                <td>{{ $item->article->name }}</td>
                <td>{{ $item->color->name }} / {{ $item->size->name }}</td>
                <td>{{ $item->packed_quantity }}</td>
                <td>${{ number_format($item->unit_price, 2) }}</td>
                <td>${{ number_format($item->packed_quantity * $item->unit_price, 2) }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

<p class="total">TOTAL: ${{ number_format($order->total_amount, 2) }}</p>