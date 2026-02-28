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
    @php
        $letra = ($order->client->getAfipTaxConditionCode() === 1) ? 'A' : 'B';
    @endphp
    <div class="invoice-box">
        <div class="type-box">{{ $letra }}</div>
        
        <table class="header">
            <tr>
                <td class="col">
                    <h1 style="margin:0; color: #000;">EL MENSUAL</h1>
                    <p><strong>Razón Social:</strong> LAMOTEX S.A.<br>
                    <strong>Domicilio:</strong> Saladillo, Buenos Aires<br>
                    <strong>Condición IVA:</strong> Responsable Inscripto</p>
                </td>
                <td class="col" style="text-align: right; border-left: 1px solid #000; padding-left: 15px;">
                    <h2 style="margin:0;">{{ $invoice->invoice_type === 'NC' ? 'NOTA DE CRÉDITO' : 'FACTURA' }}</h2>
                    <p><strong>Nro:</strong> {{ $invoice->number }}<br>
                    <strong>Fecha:</strong> {{ $invoice->created_at->format('d/m/Y') }}<br>
                    <strong>CUIT:</strong> 30-63378410-4<br>
                    <strong>Ing. Brutos:</strong> 30-63378410-4<br>
                    <strong>Inicio Actividades:</strong> 01/07/1989</p>
                </td>
            </tr>
        </table>

        <div style="margin-bottom: 15px; padding: 5px; border: 1px solid #eee;">
            <strong>CLIENTE:</strong> {{ $order->client->name }}<br>
            <strong>CUIT/DNI:</strong> {{ $order->client->tax_id ?? 'Sin CUIT' }}<br>
            <strong>IVA:</strong> {{ strtoupper($order->client->tax_condition ?? 'Consumidor Final') }}
        </div>

        <table class="items-table">
            <thead>
                <tr>
                    <th style="width: 10%;">Cant.</th>
                    <th style="width: 15%;">Código</th>
                    <th style="width: 45%;">Descripción</th>
                    <th style="width: 15%;">Precio Unit.</th>
                    <th style="width: 15%;">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                @foreach($itemsParaPdf as $item)
                <tr>
                    <td style="text-align:center;">{{ $item['qty'] }}</td>
                    <td style="text-align:center;">{{ $item['code'] }}</td>
                    <td>{{ strtoupper($item['article']) }} SIN DETALLAR</td>
                    <td style="text-align:right;">$ {{ number_format($item['price'], 2, ',', '.') }}</td>
                    <td style="text-align:right;">$ {{ number_format($item['total'], 2, ',', '.') }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>

        <div class="total-row">
            TOTAL {{ $invoice->invoice_type === 'NC' ? 'CRÉDITO' : 'FINAL' }}: $ {{ number_format(abs($invoice->total_fiscal), 2, ',', '.') }}
        </div>

        <div class="cae-data" style="position: relative; height: 80px;">
            @if(isset($qrImage))
                <img src="{{ $qrImage }}" width="70" style="position: absolute; left: 0; bottom: 0;">
            @endif
            <div style="position: absolute; right: 0; bottom: 0;">
                <strong>CAE N°:</strong> {{ $invoice->cae_afip }}<br>
                <strong>Vencimiento CAE:</strong> {{ \Carbon\Carbon::parse($invoice->cae_expiry)->format('d/m/Y') }}
            </div>
        </div>
    </div>
</body>
</html>