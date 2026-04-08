<?php

namespace App\Filament\Resources\SupplierResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class TransactionsRelationManager extends RelationManager
{
    protected static string $relationship = 'transactions';
    protected static ?string $title = 'Historial de Movimientos y Pagos';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('description')
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('origin')
                    ->label('Tipo')
                    ->badge()
                    ->color(fn ($state) => $state === 'Fiscal' ? 'info' : 'warning'),

                Tables\Columns\TextColumn::make('description')
                    ->label('Concepto / Comprobante')
                    ->searchable()
                    ->wrap(),

                Tables\Columns\TextColumn::make('amount')
                    ->label('Monto')
                    ->money('ARS')
                    ->weight('bold')
                    // Si es un pago que sacó plata (Outcome), lo mostramos en verde como un pago a favor de la deuda
                    ->color(fn ($record) => $record->type === 'Outcome' ? 'success' : 'danger'),
            ])
            ->filters([
                // Acá podrías poner filtros por fecha si quisieras
            ])
            ->headerActions([
                // Quitamos el botón de crear acá porque ya lo hacés desde la tabla principal
            ])
            ->actions([
                // Si querés poder ver el detalle o borrar un pago que te equivocaste, podés descomentar esto:
                // Tables\Actions\DeleteAction::make(),
            ]);
    }
}