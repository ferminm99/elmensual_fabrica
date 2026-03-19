<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: 'Helvetica', sans-serif; font-size: 11px; color: #111; margin: 0; }
        .invoice-box { border: 1px solid #000; padding: 15px; min-height: 95vh; }
        .type-box { position: absolute; left: 47%; top: 0; width: 45px; height: 40px; border: 1px solid #000; background: #fff; text-align: center; font-size: 30px; font-weight: bold; z-index: 10; }
        .header { width: 100%; border-bottom: 1px solid #000; margin-bottom: 15px; padding-bottom: 10px; }
        .col { width: 50%; vertical-align: top; }
        .items-table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        .items-table th { background: #f2f2f2; border: 1px solid #000; padding: 7px; font-size: 10px; }
        .items-table td { border: 1px solid #ccc; padding: 6px; }
        .total-row { text-align: right; font-size: 16px; font-weight: bold; border-top: 1px solid #000; padding-top: 10px; }
        .cae-data { margin-top: 30px; border-top: 1.5pt solid #000; padding-top: 15px; width: 100%; }
        .client-info { margin-bottom: 20px; padding: 10px; border: 1px solid #eee; background: #fafafa; }
    </style>
</head>
<body>
    @php
        // Determinamos la letra visualmente desde el invoice_type guardado
        $letra = $invoice->invoice_type; 
        if($letra === 'NC') $letra = ($order->client->tax_condition === 'Responsable Inscripto') ? 'A' : 'B';
    @endphp
    
    <div class="invoice-box">
        <div class="type-box">{{ $letra }}</div>
        
        <table class="header">
            <tr>
                <td class="col">
                    <img src="{{ public_path('images/logo.png') }}" style="max-height: 65px; margin-bottom: 5px;"><br>
                    <strong>LAMOTEX S.A.</strong><br>
                    Bartolomé Mitre 3437 - Saladillo (B.A.)<br>
                    Tel: (02344) 45-1550<br>
                    I.V.A. Responsable Inscripto
                </td>
                <td class="col" style="text-align: right; border-left: 1px solid #000; padding-left: 20px;">
                    <h2 style="margin:0; font-size: 22px;">{{ $invoice->invoice_type === 'NC' ? 'NOTA DE CRÉDITO' : 'FACTURA' }}</h2>
                    <p style="font-size: 14px; margin: 5px 0;"><strong>Nro: {{ $invoice->number }}</strong></p>
                    <p>Fecha: {{ $invoice->created_at->format('d/m/Y') }}<br>
                    CUIT: 30-63378410-4<br>
                    Ing. Brutos: 30-63378410-4<br>
                    Inicio Act.: 01/07/1989</p>
                </td>
            </tr>
        </table>

        <div class="client-info">
            <table style="width:100%">
                <tr>
                    <td style="width:60%">
                        <strong>CLIENTE:</strong> {{ strtoupper($order->client->name) }}<br>
                        <strong>DOMICILIO:</strong> {{ strtoupper($order->client->address ?? 'S/D') }}<br>
                        <strong>LOCALIDAD:</strong> {{ strtoupper($order->client->locality->name ?? '-') }}
                    </td>
                    <td style="width:40%">
                        <strong>CUIT/DNI:</strong> {{ $order->client->tax_id ?? 'Consumidor Final' }}<br>
                        <strong>IVA:</strong> {{ strtoupper($order->client->tax_condition ?? 'Consumidor Final') }}<br>
                        @if($order->remito_number)
                            <strong>REMITO:</strong> {{ str_pad($order->remito_number, 8, '0', STR_PAD_LEFT) }}
                        @endif
                    </td>
                </tr>
            </table>
        </div>

        <table class="items-table">
            <thead>
                <tr>
                    <th style="width: 10%;">Cant.</th>
                    <th style="width: 15%;">Código</th>
                    <th style="text-align: left; width: 45%;">Descripción</th>
                    <th style="text-align: right; width: 15%;">Precio Unit.</th>
                    <th style="text-align: right; width: 15%;">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                @foreach($itemsParaPdf as $item)
                <tr>
                    <td style="text-align:center; font-weight:bold;">{{ $item['qty'] }}</td>
                    <td style="text-align:center;">{{ $item['code'] }}</td>
                    <td>{{ strtoupper($item['article']) }}</td>
                    <td style="text-align:right;">$ {{ number_format($item['price'], 2, ',', '.') }}</td>
                    <td style="text-align:right;">$ {{ number_format($item['total'], 2, ',', '.') }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>

        <div class="total-row">
            TOTAL {{ $invoice->invoice_type === 'NC' ? 'CRÉDITO' : 'FINAL' }}: $ {{ number_format(abs($invoice->total_fiscal), 2, ',', '.') }}
        </div>

        <div class="cae-data">
            <table style="width: 100%;">
                <tr>
                    <td style="width: 25%;">
                        @if(!empty($qrImage))
                            <img src="data:image/svg+xml;base64,{{ $qrImage }}" style="width: 100px;">
                        @endif
                    </td>
                    <td style="width: 45%; vertical-align: middle;">
                        <div style="font-weight: bold; font-style: italic;">Comprobante Autorizado</div>
                        <div style="font-size: 9px; color: #444; margin-top: 4px;">
                            La veracidad del presente comprobante puede ser verificada en el sitio oficial de AFIP.
                        </div>
                    </td>
                    <td style="width: 30%; text-align: right; vertical-align: middle;">
                        <strong>CAE N°: {{ $invoice->cae_afip }}</strong><br>
                        Vto. CAE: {{ \Carbon\Carbon::parse($invoice->cae_expiry)->format('d/m/Y') }}
                    </td>
                </tr>
            </table>
        </div>
    </div>
</body>
</html>