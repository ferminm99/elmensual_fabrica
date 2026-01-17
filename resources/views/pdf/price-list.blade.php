<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Lista de Precios - El Mensual</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 11px; }
        .header { text-align: center; margin-bottom: 20px; }
        .logo { font-size: 20px; font-weight: bold; margin-bottom: 5px; }
        .date { text-align: right; font-size: 10px; margin-bottom: 10px; }
        
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th, td { border: 1px solid #000; padding: 4px; }
        
        /* Estilo de Categoría (Como el gris de la foto) */
        .category-header { 
            background-color: #d3d3d3; 
            font-weight: bold; 
            text-align: center; 
            padding: 6px;
            font-size: 13px;
        }
        
        .price { text-align: right; font-weight: bold; }
        .code { text-align: center; width: 10%; }
        
        .footer { 
            position: fixed; bottom: 0; width: 100%; 
            text-align: center; font-size: 9px; font-style: italic; 
            border-top: 1px solid #ccc; padding-top: 5px;
        }
    </style>
</head>
<body>

    <div class="header">
        <div class="logo">EL MENSUAL ®</div>
        <div>ROPA DE TRABAJO - BOMBACHAS DE CAMPO</div>
        <div style="margin-top: 5px; font-size: 10px;">Movil y WhatsApp: 2345 687094</div>
    </div>

    <div class="date">Fecha: {{ date('d/m/Y') }}</div>

    @foreach($categories as $category)
        {{-- Título de la Categoría (Ej: BOMBACHA RECTA) --}}
        <div style="margin-bottom: 2px; margin-top: 15px; font-weight: bold; text-align: center; font-size: 14px;">
            {{ strtoupper($category->name) }}
        </div>

        <table>
            <thead>
                <tr style="background-color: #f0f0f0;">
                    <th>ART.</th>
                    <th>MODELO / DETALLE</th>
                    <th>PRECIO</th>
                </tr>
            </thead>
            <tbody>
                @foreach($category->articles as $article)
                <tr>
                    <td class="code">{{ $article->code }}</td>
                    <td>{{ $article->name }}</td>
                    <td class="price">${{ number_format($article->base_cost, 2, ',', '.') }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    @endforeach

    <div class="footer">
        LOS PRECIOS NO INCLUYEN I.V.A. - ESTA LISTA PUEDE MODIFICARSE SIN PREVIO AVISO.
    </div>

</body>
</html>