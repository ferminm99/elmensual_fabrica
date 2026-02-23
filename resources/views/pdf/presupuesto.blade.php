<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: 'Helvetica', sans-serif; font-size: 12px; color: #111; }
        .invoice-box { border: 1px solid #000; padding: 15px; }
        .type-box { position: absolute; left: 47%; top: 0; width: 45px; height: 40px; border: 1px solid #000; background: #fff; text-align: center; font-size: 30px; font-weight: bold; z-index: 10; }
        .header { width: 100%; border-bottom: 2px solid #000; margin-bottom: 15px; padding-bottom: 10px; }
        .col { width: 50%; vertical-align: top; }
        .items-table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        .items-table th { background: #eee; border: 1px solid #000; padding: 8px; text-transform: uppercase; }
        .items-table td { border: 1px solid #ccc; padding: 8px; }
        .total-row { text-align: right; font-size: 18px; font-weight: bold; margin-top: 15px; border-top: 2px solid #000; padding-top: 10px; }
        .watermark { text-align: center; color: #aaa; font-size: 10px; margin-top: 30px; }
    </style>
</head>
<body>
    <div class="invoice-box">
        <div class="type-box">X</div>
        
        <table class="header">
            <tr>
                <td class="col">
                    <h1 style="margin:0; color: #000;">EL MENSUAL</h1>
                    <p><strong>DOCUMENTO NO VÁLIDO COMO FACTURA</strong><br>
                    Uso interno / Presupuesto</p>
                </td>
                <td class="col" style="text-align: right;">
                    <h2 style="margin:0;">PRESUPUESTO</h2>
                    <p><strong>Nro Pedido:</strong> #{{ $order->id }}<br>
                    <strong>Fecha:</strong> {{ now()->format('d/m/Y') }}<br>
                </td>
            </tr>
        </table>

        <div style="margin-bottom: 15px; padding: 10px; background: #f9f9f9; border: 1px solid #ddd;">
            <strong>CLIENTE:</strong> {{ $order->client->name }}<br>
            <strong>ZONA/LOCALIDAD:</strong> {{ $order->client->locality->name ?? '-' }}
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
                @foreach($itemsParaPresupuesto as $item)
                <tr>
                    <td>{{ $item['article'] }} ({{ $item['color'] }} - {{ $item['size'] }})</td>
                    <td style="text-align:center;">{{ $item['qty'] }}</td>
                    <td style="text-align:right;">$ {{ number_format($item['price'], 2, ',', '.') }}</td>
                    <td style="text-align:right;">$ {{ number_format($item['subtotal'], 2, ',', '.') }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>

        <div class="total-row">
            TOTAL PRESUPUESTO: $ {{ number_format($totalPresupuesto, 2, ',', '.') }}
        </div>
        
        <div class="watermark">
            DOCUMENTO DE CONTROL INTERNO - NO VÁLIDO COMO COMPROBANTE FISCAL
        </div>
    </div>
</body>
</html>