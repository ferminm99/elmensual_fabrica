<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: sans-serif; font-size: 10px; margin: 0; padding: 0; }
        .remito-body { height: 32%; border-bottom: 1px dashed #000; padding: 10px; position: relative; }
        .remito-body:last-child { border-bottom: none; }
        .header { width: 100%; font-weight: bold; margin-bottom: 5px; }
        .tipo-remito { position: absolute; top: 10px; right: 10px; border: 1px solid #000; padding: 5px; font-size: 14px; }
        table { width: 100%; border-collapse: collapse; margin-top: 5px; }
        th { border: 1px solid #000; background: #eee; padding: 3px; }
        td { border: 1px solid #ccc; padding: 3px; }
        .footer { margin-top: 5px; font-style: italic; }
    </style>
</head>
<body>
    @php
        $copias = [
            ['titulo' => 'ORIGINAL (FÁBRICA)', 'qty_key' => 'qty_100'],
            ['titulo' => 'DUPLICADO (TRANSPORTE)', 'qty_key' => 'qty_50'],
            ['titulo' => 'TRIPLICADO (CLIENTE)', 'qty_key' => 'qty_50']
        ];
    @endphp

    @foreach($copias as $copia)
        <div class="remito-body">
            <div class="tipo-remito">R</div>
            <table class="header">
                <tr>
                    <td>
                        <span style="font-size: 16px;">EL MENSUAL - REMITO</span><br>
                        Pedido #{{ $order->id }} - {{ $copia['titulo'] }}
                    </td>
                    <td style="text-align: right;">
                        Fecha: {{ now()->format('d/m/Y') }}<br>
                        Cliente: {{ $order->client->name }}
                    </td>
                </tr>
            </table>

            <table>
                <thead>
                    <tr>
                        <th style="text-align: left;">Artículo / Variante</th>
                        <th style="width: 60px;">Cant.</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($itemsParaRemito as $item)
                        <tr>
                            <td>{{ $item['article'] }} ({{ $item['color'] }} - {{ $item['size'] }})</td>
                            <td style="text-align: center;">{{ $item[$copia['qty_key']] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <div class="footer">
                Recibió: ___________________________ Bultos: ____ Transporte: ________________
            </div>
        </div>
    @endforeach
</body>
</html>