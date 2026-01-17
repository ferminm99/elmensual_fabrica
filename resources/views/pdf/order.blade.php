<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Orden #{{ $order->id }}</title>
    <style>
        body { font-family: sans-serif; font-size: 14px; }
        .header { width: 100%; border-bottom: 2px solid #333; margin-bottom: 20px; padding-bottom: 10px; }
        .company-info { float: left; }
        .order-info { float: right; text-align: right; }
        .title { font-size: 24px; font-weight: bold; margin-bottom: 5px; }
        .table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .table th, .table td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        .table th { background-color: #f2f2f2; }
        .totals { margin-top: 20px; text-align: right; }
        .totals p { font-size: 16px; margin: 5px 0; }
        .footer { margin-top: 50px; text-align: center; font-size: 12px; color: #777; border-top: 1px solid #ddd; padding-top: 10px; }
    </style>
</head>
<body>

    <div class="header">
        <div class="company-info">
            <div class="title">EL MENSUAL</div>
            <p>Fábrica Textil</p>
            <p>La Plata, Buenos Aires</p>
        </div>
        <div class="order-info">
            <p><strong>Orden N°:</strong> {{ $order->id }}</p>
            <p><strong>Fecha:</strong> {{ $order->created_at->format('d/m/Y') }}</p>
            <p><strong>Cliente:</strong> {{ $order->client->name }}</p>
            <p><strong>CUIT:</strong> {{ $order->client->tax_id ?? 'Consumidor Final' }}</p>
        </div>
        <div style="clear: both;"></div>
    </div>

    <h3>Detalle del Pedido (Origen: {{ $order->origin }})</h3>

    <table class="table">
        <thead>
            <tr>
                <th>Producto</th>
                <th>Cant.</th>
                <th>Precio Unit.</th>
                <th>Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @foreach($order->items as $item)
            <tr>
                <td>{{ $item->sku->article->name }} - {{ $item->sku->size->name }}/{{ $item->sku->color->name }}</td>
                <td>{{ $item->quantity }}</td>
                <td>${{ number_format($item->unit_price, 2) }}</td>
                <td>${{ number_format($item->quantity * $item->unit_price, 2) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="totals">
        <p><strong>Total: ${{ number_format($order->total, 2) }}</strong></p>
    </div>

    <div class="footer">
        <p>Documento no válido como factura fiscal - Uso Interno de Control</p>
    </div>

</body>
</html>