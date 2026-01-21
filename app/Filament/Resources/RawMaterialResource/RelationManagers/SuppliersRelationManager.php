<?php

namespace App\Filament\Resources\RawMaterialResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class SuppliersRelationManager extends RelationManager
{
    protected static string $relationship = 'suppliers';

    // Título de la pestaña
    protected static ?string $title = 'Comparativa de Precios';

    // Campo que se muestra en el título del modal al editar
    protected static ?string $recordTitleAttribute = 'name';

    public function form(Form $form): Form
    {
        // Este form es para EDITAR AL PROVEEDOR (Nombre, CUIT), no el precio.
        // Lo dejamos bloqueado para no cambiar datos del proveedor desde acá por error.
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->disabled(), // Solo lectura
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Proveedores y Costos')
            ->description('Lista de proveedores que venden este insumo, ordenada por precio.')
            ->defaultSort('price', 'asc') // Ordenar por columna pivote 'price'

            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Proveedor')
                    ->weight('bold')
                    ->icon('heroicon-o-truck'),

                // COLUMNA DEL PIVOTE (PRECIO)
                Tables\Columns\TextColumn::make('pivot.price')
                    ->label('Costo Unitario')
                    ->money('ARS')
                    ->weight('black')
                    ->sortable()
                    ->color(fn ($state) => $state == 0 ? 'danger' : 'success'),

                Tables\Columns\TextColumn::make('phone')
                    ->label('Teléfono')
                    ->color('gray'),
            ])
            ->headerActions([
                // ASOCIAR UN PROVEEDOR EXISTENTE A ESTE INSUMO
                Tables\Actions\AttachAction::make()
                    ->label('Agregar Proveedor')
                    ->preloadRecordSelect()
                    ->form(fn (Tables\Actions\AttachAction $action): array => [
                        $action->getRecordSelect(),
                        // Campo extra para la tabla intermedia
                        Forms\Components\TextInput::make('price')
                            ->label('Precio de Costo Pactado')
                            ->numeric()
                            ->prefix('$')
                            ->required(),
                    ]),
            ])
            ->actions([
                // EDITAR EL PRECIO (PIVOTE)
                Tables\Actions\EditAction::make()
                    ->label('Actualizar Precio')
                    ->icon('heroicon-o-currency-dollar')
                    ->form([
                        // Al editar, solo mostramos el campo PRECIO de la tabla intermedia
                        Forms\Components\TextInput::make('price')
                            ->label('Nuevo Precio')
                            ->numeric()
                            ->prefix('$')
                            ->required(),
                    ]),

                Tables\Actions\DetachAction::make()->label('Quitar'),
            ]);
    }
}