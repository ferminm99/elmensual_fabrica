<?php

namespace App\Filament\Resources\ClientResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class PaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'payments';

    protected static ?string $title = 'Historial de Pagos';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('amount')
                    ->label('Monto')
                    ->prefix('$')
                    ->disabled(),
                Forms\Components\TextInput::make('description')
                    ->label('Descripción')
                    ->disabled(),
                Forms\Components\DateTimePicker::make('created_at')
                    ->label('Fecha')
                    ->disabled(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                // 1. FECHA
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                // 2. MONTO
                Tables\Columns\TextColumn::make('amount')
                    ->label('Monto')
                    ->money('ARS')
                    ->weight('bold')
                    ->color('success'),

                // 3. DETALLE
                Tables\Columns\TextColumn::make('description')
                    ->label('Detalle')
                    ->searchable()
                    ->limit(30),

                // 4. ORIGEN (CORREGIDO PARA ENUMS)
                Tables\Columns\TextColumn::make('origin')
                    ->label('Tipo')
                    ->badge()
                    // Quitamos 'string $state' y usamos lógica segura
                    ->color(fn ($state) => match ($state->name ?? $state) {
                        'Fiscal' => 'success',   // Verde
                        'Internal' => 'warning', // Naranja
                        default => 'gray',
                    })
                    ->formatStateUsing(fn ($state) => match ($state->name ?? $state) {
                        'Fiscal' => 'En Blanco',
                        'Internal' => 'En Negro',
                        default => $state,
                    }),
                
                // 5. CUENTA DESTINO
                Tables\Columns\TextColumn::make('companyAccount.name')
                    ->label('Cuenta Destino')
                    ->icon('heroicon-o-building-library')
                    ->color('gray'),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                // Sin acciones de edición para mantener integridad
            ]);
    }
}