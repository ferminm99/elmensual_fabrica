<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Table;
use Filament\Tables;
use App\Models\Invoice;
use Illuminate\Database\Eloquent\Builder;

class LibroIvaVentas extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationGroup = 'Contabilidad';
    protected static ?string $title = 'Libro de I.V.A. Ventas';
    protected static string $view = 'filament.pages.libro-iva-ventas';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                // Traemos solo las facturas que tengan CAE (Facturadas en Blanco)
                // Y cargamos la relación order.client para poder sacar el Nombre y el CUIT
                Invoice::query()
                    ->whereNotNull('cae_afip')
                    ->with(['order.client'])
                    ->orderBy('created_at', 'desc')
            )
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Fecha')
                    ->date('d/m/Y')
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('order.client.name')
                    ->label('Cliente')
                    ->searchable(),

                Tables\Columns\TextColumn::make('order.client.tax_id')
                    ->label('CUIT')
                    ->searchable(),

                Tables\Columns\TextColumn::make('invoice_type')
                    ->label('Tipo')
                    ->badge()
                    ->color('success'),

                Tables\Columns\TextColumn::make('number')
                    ->label('Comprobante')
                    ->searchable(),

                // Calculamos el Neto Gravado (Total / 1.21)
                Tables\Columns\TextColumn::make('neto')
                    ->label('Neto Gravado')
                    ->money('ARS')
                    ->state(fn (Invoice $record) => round($record->total_fiscal / 1.21, 2))
                    ->alignRight(),

                // Calculamos el IVA (Total - Neto)
                Tables\Columns\TextColumn::make('iva')
                    ->label('I.V.A. (21%)')
                    ->money('ARS')
                    ->state(fn (Invoice $record) => round($record->total_fiscal - ($record->total_fiscal / 1.21), 2))
                    ->alignRight(),

                Tables\Columns\TextColumn::make('total_fiscal')
                    ->label('Total Factura')
                    ->money('ARS')
                    ->weight('bold')
                    ->alignRight(),
            ])
            ->filters([
                // A futuro: Filtros por mes
            ])
            ->headerActions([
                // BOTÓN PARA EXPORTAR A ARCA (Ventas)
                Tables\Actions\Action::make('export_arca_ventas')
                    ->label('Exportar TXT Ventas')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('info')
                    ->action(function () {
                        // SOLUCIÓN: Buscamos directamente en la base de datos
                        $records = \App\Models\Invoice::with('order.client')
                            ->whereNotNull('cae_afip')
                            ->orderBy('created_at', 'desc')
                            ->get();
                        
                        $contenidoTxt = "";
                        foreach ($records as $record) {
                            $fecha = $record->created_at->format('Ymd');
                            $cuit = str_replace('-', '', $record->order->client->tax_id ?? '0');
                            $netoCalc = round($record->total_fiscal / 1.21, 2);
                            $neto = number_format($netoCalc, 2, '', '');
                            $total = number_format($record->total_fiscal, 2, '', '');
                            
                            $contenidoTxt .= "{$fecha}|{$record->invoice_type}|{$record->number}|{$cuit}|{$neto}|{$total}\r\n";
                        }

                        return response()->streamDownload(function () use ($contenidoTxt) {
                            echo $contenidoTxt;
                        }, 'Libro_IVA_Ventas_ARCA.txt');
                    }),
            ]);
    }
}