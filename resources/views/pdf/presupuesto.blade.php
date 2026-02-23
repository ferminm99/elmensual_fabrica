<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: 'Helvetica', sans-serif; font-size: 11px; color: #000; }
        .invoice-box { padding: 10px; }
        .header { width: 100%; margin-bottom: 20px; border-bottom: 2px solid #000; padding-bottom: 10px; }
        /* APLICANDO EL MISMO TAMAÑO DE LOGO */
        .logo { width: 65px; }
        .doc-info { text-align: right; font-size: 12px; }
        .client-info { margin-bottom: 20px; font-size: 11px; line-height: 1.4; }
        
        .items-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .items-table th { border-top: 1px solid #000; border-bottom: 1px solid #000; padding: 6px 0; text-align: left; text-transform: uppercase; font-size: 10px; }
        .items-table td { padding: 6px 0; vertical-align: top; font-size: 10px; }
        
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        
        .totals-table { width: 100%; margin-top: 20px; }
        .total-row { font-size: 14px; font-weight: bold; }
        .totals-border { border-top: 2px solid #000; padding-top: 5px; }
    </style>
</head>
<body>
    <div class="invoice-box">
        <table class="header">
            <tr>
                <td style="width: 50%; vertical-align: top;">
                    {{-- REEMPLAZO TEXTO POR LOGO --}}
                    <img src="{{ public_path('images/logo.png') }}" class="logo"><br>
                    <span style="font-weight: bold; font-size: 14px;">EL MENSUAL</span><br>
                    <span style="font-size: 9px; color: #555;">USO INTERNO / NO VÁLIDO COMO FACTURA</span>
                </td>
                <td style="width: 50%; vertical-align: top;" class="doc-info">
                    <h2 style="margin:0;">PRESUPUESTO X</h2>
                    <strong>FC01-X 00000-{{ str_pad($order->id, 8, '0', STR_PAD_LEFT) }}</strong><br><br>
                    Fecha: {{ now()->format('d/m/y') }}
                </td>
            </tr>
        </table>

        <div class="client-info">
            {{ str_pad($order->client->id, 5, '0', STR_PAD_LEFT) }} &nbsp;&nbsp;&nbsp; {{ strtoupper($order->client->name) }}<br>
            {{ strtoupper($order->client->address ?? 'DOMICILIO S/D') }}<br>
            {{ strtoupper($order->client->tax_condition ?? 'CONSUMIDOR FINAL') }} &nbsp;&nbsp;&nbsp; {{ $order->client->tax_id ?? '' }}<br>
            CUENTA CORRIENTE
        </div>

        <table class="items-table">
            <thead>
                <tr>
                    <th style="width: 10%;" class="text-center">Cantidad</th>
                    <th style="width: 15%;">Código</th>
                    <th style="width: 45%;">Descripción</th>
                    <th style="width: 15%;" class="text-right">Precio</th>
                    <th style="width: 15%;" class="text-right">TOTAL</th>
                </tr>
            </thead>
            <tbody>
                @foreach($itemsParaPresupuesto as $item)
                <tr>
                    <td class="text-center">{{ $item['qty'] }}</td>
                    <td>{{ $item['code'] }}</td>
                    <td>{{ strtoupper($item['article']) }} SIN DETALLAR</td>
                    <td class="text-right">{{ number_format($item['price'], 2, ',', '') }}</td>
                    <td class="text-right">{{ number_format($item['subtotal'], 2, ',', '') }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>

        <table class="totals-table">
            <tr>
                <td style="width: 70%;"></td>
                <td style="width: 15%; text-align: right; font-weight: bold; padding-right: 15px;" class="totals-border">
                    TOTAL
                </td>
                <td style="width: 15%; text-align: right;" class="totals-border total-row">
                    {{ number_format($totalPresupuesto, 2, ',', '') }}
                </td>
            </tr>
        </table>
    </div>
</body>
</html>