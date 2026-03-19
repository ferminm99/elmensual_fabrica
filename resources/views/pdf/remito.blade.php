<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 0.5cm; }
        body { font-family: 'Helvetica', sans-serif; font-size: 9px; color: #000; margin: 0; padding: 10px; }
        
        /* CONTENEDOR PRINCIPAL QUE ENCUADRA TODO */
        .main-frame { 
            border: 1.5pt solid #000; 
            width: 100%; 
            position: relative;
            box-sizing: border-box;
        }

        .remito-wrapper { padding-bottom: 20px; }
        .remito-wrapper + .remito-wrapper { page-break-before: always; margin-top: 10px; }

        /* TABLAS INTERNAS: Quitamos bordes externos para usar el del frame */
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        
        /* SECCIÓN CABECERA */
        .header-box td { border-bottom: 1pt solid #000; padding: 8px; vertical-align: top; }
        .logo { width: 65px; margin-bottom: 2px; }
        
        .center-type { text-align: center; width: 90px; border-left: 1pt solid #000; border-right: 1pt solid #000; }
        .box-letter { border: 1pt solid #000; font-size: 26px; font-weight: bold; padding: 2px 10px; display: inline-block; margin-top: 2px; }
        .sub-text { font-size: 6px; line-height: 1.1; text-transform: uppercase; font-weight: bold; }

        /* SECCIÓN CLIENTE */
        .client-box td { border-bottom: 1pt solid #000; padding: 4px 8px; font-size: 10px; line-height: 1.4; vertical-align: top; }
        .label { font-weight: bold; display: inline-block; width: 65px; }

        /* BARRA CONDICIÓN DE PAGO */
        .payment-bar { 
            border-bottom: 1pt solid #000; 
            padding: 4px 8px; 
            font-size: 9px; 
            font-weight: bold; 
            text-transform: uppercase; 
        }

        /* TABLA DE ITEMS */
        .items-table th { border-bottom: 1pt solid #000; padding: 5px; text-transform: uppercase; font-size: 8px; background: #f2f2f2; border-right: 1pt solid #000; }
        .items-table th:last-child { border-right: none; }
        .items-table td { padding: 4px 8px; font-size: 9px; border-right: 1pt solid #000; height: 18px; }
        .items-table td:last-child { border-right: none; }

        /* SECCIÓN DE TOTALES */
        .totals-container { border-top: 1pt solid #000; width: 100%; }
        .totals-table { width: 40%; float: right; border-left: 1pt solid #000; border-collapse: collapse; }
        .totals-table td { padding: 4px 8px; font-size: 10px; border-bottom: 1pt solid #000; }
        .totals-table tr:last-child td { border-bottom: none; } /* El cierre lo da el frame */
        .t-label { text-align: left; font-weight: bold; border-right: 1pt solid #000; }
        .t-value { text-align: right; }
        .final-row { background: #eee; font-weight: bold; font-size: 12px; }

        /* PIE FUERA DEL FRAME PRINCIPAL */
        .footer-info { clear: both; width: 100%; padding: 10px 0; }
        .cae-section { float: right; text-align: right; font-size: 10px; font-weight: bold; }
        .copy-type { float: left; font-size: 11px; font-weight: bold; text-transform: uppercase; }
    </style>
</head>
<body>
    @php
        $copias = [['t' => 'ORIGINAL (FÁBRICA)'], ['t' => 'DUPLICADO (TRANSPORTE)'], ['t' => 'TRIPLICADO (CLIENTE)']];
    @endphp

    @foreach($copias as $copia)
    <div class="remito-wrapper">
        <div class="main-frame">
            <table class="header-box">
                <tr>
                    <td style="width: 45%;">
                        <img src="{{ public_path('images/logo.png') }}" class="logo"><br>
                        <strong style="font-size: 14px;">LAMOTEX S.A.</strong><br>
                        Bartolomé Mitre 3437 - Saladillo (B.A.)<br>
                        Tel: (02344) 45-1550<br>
                        <strong>I.V.A. RESPONSABLE INSCRIPTO</strong>
                    </td>
                    <td class="center-type">
                        <div class="box-letter">{{ $letra }}</div><br>
                        <div class="sub-text">COD. N° {{ $letra === 'R' ? '91' : '01' }}</div>
                        @if($letra === 'R')
                            <div class="sub-text" style="margin-top:5px">DOC. NO VALIDO<br>COMO FACTURA</div>
                        @endif
                    </td>
                    <td style="width: 45%; text-align: right;">
                        <h1 style="margin: 0; font-size: 20px;">{{ $tipoDoc }}</h1>
                        <strong style="font-size: 15px;">N° {{ str_pad($settings->remito_pv ?? 1, 5, '0', STR_PAD_LEFT) }}-{{ str_pad($order->remito_number ?? 0, 8, '0', STR_PAD_LEFT) }}</strong><br>
                        <strong style="font-size: 10px;">FECHA: {{ now()->format('d/m/Y') }}</strong><br>
                        <div style="font-size: 8px; margin-top: 5px; line-height: 1.2;">
                            C.U.I.T. N°: 30-63378410-4<br>
                            Ing. Brutos N°: 30-63378410-4<br>
                            Inicio Actividades: 01/07/1989
                        </div>
                    </td>
                </tr>
            </table>

            <table class="client-box">
                <tr>
                    <td style="width: 60%; border-right: 1pt solid #000;">
                        <span class="label">Señores:</span> {{ str_pad($order->client->id, 5, '0', STR_PAD_LEFT) }} &nbsp; {{ strtoupper($order->client->name) }}<br>
                        <span class="label">Domicilio:</span> {{ strtoupper($order->client->address ?? 'S/D') }}<br>
                        <span class="label">Localidad:</span> {{ strtoupper($order->client->locality->name ?? 'S/D') }} ({{ $order->client->locality->postal_code ?? '' }})
                    </td>
                    <td style="width: 40%;">
                        <span class="label">Provincia:</span> {{ strtoupper($order->client->locality->province ?? 'BS. AS.') }}<br>
                        <span class="label">I.V.A.:</span> {{ strtoupper($order->client->tax_condition ?? 'Consumidor Final') }}<br>
                        <span class="label">C.U.I.T.:</span> {{ $order->client->tax_id ?? 'S/D' }}
                    </td>
                </tr>
            </table>

            <div class="payment-bar">
                Condición de Pago: {{ strtoupper($order->payment_method ?? 'CUENTA CORRIENTE') }}
            </div>

            <table class="items-table">
                <thead>
                    <tr>
                        <th style="width: 10%;">Cant.</th>
                        <th style="width: 15%;">Código</th>
                        <th style="text-align: left; width: 45%;">Descripción</th>
                        <th style="text-align: right; width: 15%;">Precio U.</th>
                        <th style="text-align: right; width: 15%;">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($itemsParaPdf as $item)
                    <tr>
                        <td style="text-align: center; font-weight: bold;">{{ $item['qty'] }}</td>
                        <td style="text-align: center;">{{ $item['code'] }}</td>
                        <td>{{ strtoupper($item['article']) }} SIN DETALLAR</td>
                        <td style="text-align: right;">$ {{ number_format($item['price'], 2, ',', '.') }}</td>
                        <td style="text-align: right;">$ {{ number_format($item['total'], 2, ',', '.') }}</td>
                    </tr>
                    @endforeach
                    {{-- Espacio mínimo para mantener la altura visual --}}
                    @for ($i = count($itemsParaPdf); $i < 10; $i++)
                    <tr><td>&nbsp;</td><td></td><td></td><td></td><td></td></tr>
                    @endfor
                </tbody>
            </table>

            <div class="totals-container">
                <table class="totals-table">
                    <tr>
                        <td class="t-label">Subtotal</td>
                        <td class="t-value">$ {{ number_format($totales['bruto'], 2, ',', '.') }}</td>
                    </tr>
                    @if($totales['dto_p'] > 0)
                    <tr>
                        <td class="t-label">Descuento ({{ $totales['dto_p'] }}%)</td>
                        <td class="t-value">- $ {{ number_format($totales['dto_m'], 2, ',', '.') }}</td>
                    </tr>
                    @endif
                    @if($esFiscal)
                    <tr>
                        <td class="t-label">I.V.A. (21%)</td>
                        <td class="t-value">$ {{ number_format($totales['iva'], 2, ',', '.') }}</td>
                    </tr>
                    @endif
                    <tr class="final-row">
                        <td class="t-label">TOTAL FINAL</td>
                        <td class="t-value">$ {{ number_format($totales['total'], 2, ',', '.') }}</td>
                    </tr>
                </table>
                <div style="clear: both;"></div>
            </div>
            <div style="margin-top: 20px; padding: 10px; border-top: 1pt solid #000;">
                <table style="width: 100%; border: none;">
                    <tr>
                        <td style="width: 50%; border: none;">
                            <br><br>
                            ............................................................<br>
                            <span style="font-size: 8px;">FIRMA Y ACLARACIÓN DEL RECEPTOR</span>
                        </td>
                        <td style="width: 50%; text-align: right; border: none;">
                            <br><br>
                            ............................................................<br>
                            <span style="font-size: 8px;">D.N.I. / SELLO</span>
                        </td>
                    </tr>
                </table>
            </div>
        </div> <div class="footer-info">
            <div class="copy-type">{{ $copia['t'] }}</div>
            <div class="cae-section">
                @if($esFiscal && $invoice)
                    C.A.E. N°: {{ $invoice->cae_afip }} &nbsp;&nbsp; Vto: {{ \Carbon\Carbon::parse($invoice->cae_expiry)->format('d/m/Y') }}
                @else
                    CAI N°: {{ $settings->cai_number }} &nbsp;&nbsp; Vto: {{ \Carbon\Carbon::parse($settings->cai_expiry)->format('d/m/Y') }}
                @endif
            </div>
        </div>
        @if(isset($qrImage) && $qrImage)
            <div style="position: absolute; bottom: 30px; left: 15px; text-align: left; width: 250px;">
                <img src="{{ $qrImage }}" width="70" style="float: left; margin-right: 10px;">
                <div style="font-size: 7px; margin-top: 15px;">
                    <strong>Comprobante Autorizado</strong><br>
                    La veracidad del presente comprobante podrá ser<br>
                    verificada ingresando a la web de AFIP/ARCA.
                </div>
            </div>
        @endif
    </div>
    @endforeach
</body>
</html>