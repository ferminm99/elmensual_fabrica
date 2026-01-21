<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Orden de Armado #{{ $order->id }}</title>
    <style>
        body { font-family: sans-serif; font-size: 14px; }
        .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #333; padding-bottom: 10px; }
        .info-table { width: 100%; margin-bottom: 20px; }
        .info-table td { padding: 5px; }
        
        .group-header { 
            background-color: #e5e7eb; 
            padding: 8px; 
            font-weight: bold; 
            border: 1px solid #000; 
            margin-top: 15px; 
        }
        
        .items-table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        .items-table th, .items-table td { border: 1px solid #000; padding: 8px; text-align: center; }
        .items-table th { background-color: #f3f4f6; }
        
        .check-col { width: 50px; }
        .total-box { text-align: right; font-size: 18px; font-weight: bold; margin-top: 30px; }
    </style>
</head>
<body>

    <div class="header">
        <h1>Hoja de Armado / Picking List</h1>
        <h2>Pedido #{{ $order->id }}</h2>
    </div>

    <table class="info-table">
        <tr>
            <td><strong>Cliente:</strong> {{ $order->client->name }}</td>
            <td><strong>Fecha del Pedido:</strong> {{ \Carbon\Carbon::parse($order->order_date)->format('d/m/Y') }}</td>
        </tr>
        <tr>
            <td>
                <strong>Estado:</strong> 
                {{ $order->status instanceof \App\Enums\OrderStatus ? $order->status->getLabel() : $order->status }}
            </td>
            <td><strong>Fecha Impresión:</strong> {{ now()->format('d/m/Y H:i') }}</td>
        </tr>
    </table>

    {{-- LÓGICA DE AGRUPAMIENTO SEGURA --}}
    @php
        $groupedItems = $order->items->groupBy(function($item) {
            // VERIFICACIÓN DE SEGURIDAD:
            // Si el ítem no tiene artículo asociado (por datos viejos o error), lo mandamos a un grupo aparte
            if (!$item->article) {
                return '⚠️ ARTÍCULO DESCONOCIDO / BORRADO';
            }
            return $item->article->code . ' - ' . $item->article->name;
        });
        
        $grandTotal = 0;
    @endphp

    @foreach($groupedItems as $articleName => $items)
        <div class="group-header">
            {{ $articleName }}
        </div>

        <table class="items-table">
            <thead>
                <tr>
                    <th>Color</th>
                    <th>Talle</th>
                    <th>Cantidad</th>
                    <th class="check-col">Check</th>
                </tr>
            </thead>
            <tbody>
                @foreach($items as $item)
                    <tr>
                        <td style="text-align: left; padding-left: 15px;">
                            @if($item->color)
                                <div style="display: flex; align-items: center;">
                                    {{-- El Círculo de Color --}}
                                    <span style="
                                        display: inline-block;
                                        width: 12px;
                                        height: 12px;
                                        border-radius: 50%;
                                        background-color: {{ $item->color->hex_code ?? '#ccc' }};
                                        border: 1px solid #333;
                                        margin-right: 8px;
                                    "></span>
                                    
                                    {{-- El Nombre --}}
                                    {{ $item->color->name }}
                                </div>
                            @else
                                <span>Indefinido</span>
                            @endif
                        </td>
                        <td>
                            {{-- Doble chequeo de seguridad para Talle --}}
                            {{ $item->sku && $item->sku->size ? $item->sku->size->name : 'Indefinido' }}
                        </td>
                        <td style="font-weight: bold; font-size: 1.1em;">{{ $item->quantity }}</td>
                        <td></td> 
                    </tr>
                    @php $grandTotal += $item->quantity; @endphp
                @endforeach
            </tbody>
        </table>
    @endforeach

    <div class="total-box">
        TOTAL PRENDAS: {{ $grandTotal }}
    </div>

</body>
</html>