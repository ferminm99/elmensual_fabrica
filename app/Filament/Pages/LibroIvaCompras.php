<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Table;
use Filament\Tables;
use App\Models\SupplierInvoice;

class LibroIvaCompras extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-book-open';
    protected static ?string $navigationGroup = 'Contabilidad';
    protected static ?string $title = 'Libro de I.V.A. Compras';
    protected static string $view = 'filament.pages.libro-iva-compras';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                // AHORA LEE DE LA TABLA CORRECTA
                SupplierInvoice::query()->with('supplier')->orderBy('fecha_emision', 'desc')
            )
            ->columns([
                Tables\Columns\TextColumn::make('fecha_emision')->label('Fecha')->date('d/m/Y')->sortable(),
                Tables\Columns\TextColumn::make('supplier.name')->label('Proveedor')->searchable(),
                Tables\Columns\TextColumn::make('supplier.tax_id')->label('CUIT'),
                Tables\Columns\TextColumn::make('tipo_comprobante')->label('Tipo')->badge()->color('warning'),
                Tables\Columns\TextColumn::make('numero')->label('Comprobante')->searchable(),
                Tables\Columns\TextColumn::make('neto_gravado')->label('Neto')->money('ARS')->alignRight(),
                Tables\Columns\TextColumn::make('iva')->label('I.V.A.')->money('ARS')->alignRight(),
                
                Tables\Columns\TextColumn::make('otros_impuestos')
                    ->label('Otros Imp.')
                    ->money('ARS')
                    ->state(fn ($record) => $record->no_gravado + $record->exento + $record->perc_iva + $record->perc_iibb + $record->perc_imp_internos)
                    ->alignRight(),

                Tables\Columns\TextColumn::make('total')->label('Total Factura')->money('ARS')->weight('bold')->alignRight(),
            ])
            ->headerActions([
                Tables\Actions\Action::make('export_arca')
                    ->label('Exportar TXT Compras')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->action(function () {
                        // EL MISMO ARREGLO QUE HICIMOS EN VENTAS
                        $records = SupplierInvoice::with('supplier')->orderBy('fecha_emision', 'asc')->get();
                        
                        $contenidoTxt = "";
                        foreach ($records as $record) {
                            $fecha = \Carbon\Carbon::parse($record->fecha_emision)->format('Ymd');
                            $cuit = str_replace('-', '', $record->supplier->tax_id ?? '0');
                            $neto = number_format($record->neto_gravado, 2, '', '');
                            $total = number_format($record->total, 2, '', '');
                            
                            $contenidoTxt .= "{$fecha}|{$record->tipo_comprobante}|{$record->numero}|{$cuit}|{$neto}|{$total}\r\n";
                        }

                        return response()->streamDownload(function () use ($contenidoTxt) {
                            echo $contenidoTxt;
                        }, 'Libro_IVA_Compras_ARCA.txt');
                    }),
            ]);
    }
}