<?php

namespace App\Filament\Resources\SupplierResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms\Set;
use Filament\Forms\Get;

// La misma función que usamos para calcular totales
function calcularTotalEdicion(Set $set, Get $get) {
    $total = (float) ($get('neto_gravado') ?: 0) + (float) ($get('no_gravado') ?: 0) + (float) ($get('exento') ?: 0) + (float) ($get('iva') ?: 0) + (float) ($get('perc_iva') ?: 0) + (float) ($get('perc_iibb') ?: 0) + (float) ($get('perc_imp_internos') ?: 0);
    $set('total', round($total, 2));
}

class SupplierInvoicesRelationManager extends RelationManager
{
    protected static string $relationship = 'supplierInvoices';
    protected static ?string $title = 'Facturas Cargadas';
    protected static ?string $modelLabel = 'Factura';

    public function form(Form $form): Form
    {
        $noSpinnersClass = '[appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none';

        return $form
            ->schema([
                Forms\Components\Select::make('tipo_comprobante')->label('Tipo')->options(['01' => 'Factura A (01)', '06' => 'Factura B (06)', '11' => 'Factura C (11)', '02' => 'Nota Débito A (02)', '03' => 'Nota Crédito A (03)', 'X' => 'Remito / Interno (Negro)'])->required(),
                Forms\Components\TextInput::make('numero')->label('Nro Comprobante (Ej: 0001-00001234)')->required(),
                Forms\Components\DatePicker::make('fecha_emision')->label('Fecha')->required(),
                Forms\Components\TextInput::make('cae')->label('CAE')->numeric(),
                
                Forms\Components\Grid::make(4)->schema([
                    Forms\Components\TextInput::make('neto_gravado')->numeric()->prefix('$')->live(debounce: 500)->afterStateUpdated(fn (Set $set, Get $get) => calcularTotalEdicion($set, $get)),
                    Forms\Components\TextInput::make('no_gravado')->numeric()->prefix('$')->live(debounce: 500)->afterStateUpdated(fn (Set $set, Get $get) => calcularTotalEdicion($set, $get)),
                    Forms\Components\TextInput::make('exento')->numeric()->prefix('$')->live(debounce: 500)->afterStateUpdated(fn (Set $set, Get $get) => calcularTotalEdicion($set, $get)),
                    Forms\Components\TextInput::make('iva')->numeric()->prefix('$')->live(debounce: 500)->afterStateUpdated(fn (Set $set, Get $get) => calcularTotalEdicion($set, $get)),
                    Forms\Components\TextInput::make('total')->label('TOTAL')->numeric()->prefix('$')->required()->readOnly()->extraInputAttributes(['class' => 'font-bold text-primary-600 bg-gray-100']),
                ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('fecha_emision')->label('Fecha')->date('d/m/Y')->sortable(),
                Tables\Columns\TextColumn::make('tipo_comprobante')->label('Tipo'),
                Tables\Columns\TextColumn::make('numero')->label('Número')->searchable(),
                Tables\Columns\TextColumn::make('total')->label('Total')->money('ARS')->weight('bold'),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->using(function (\Illuminate\Database\Eloquent\Model $record, array $data): \Illuminate\Database\Eloquent\Model {
                        // MAGIA: Calculamos si le cambiaste el importe para arreglarle la deuda al proveedor
                        $montoViejo = $record->total;
                        $montoNuevo = $data['total'] ?? 0;
                        $diferencia = $montoNuevo - $montoViejo;

                        $record->update($data); // Guardamos la factura corregida

                        // Ajustamos el saldo
                        if ($diferencia != 0) {
                            $record->supplier->increment('account_balance_fiscal', $diferencia);
                        }

                        return $record;
                    }),
                    
                Tables\Actions\DeleteAction::make()
                    ->action(function (\Illuminate\Database\Eloquent\Model $record) {
                        // Si eliminás la factura, le restamos esa deuda al proveedor
                        $record->supplier->decrement('account_balance_fiscal', $record->total);
                        $record->delete();
                    }),
            ]);
    }
}