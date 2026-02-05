<?php

namespace App\Filament\Resources\ClientResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Models\Order;
use App\Enums\Origin;

class OrdersRelationManager extends RelationManager
{
  protected static string $relationship = 'orders';

    protected static ?string $title = 'Historial de Compras'; // Título de la pestaña
    
    protected static ?string $icon = 'heroicon-o-shopping-bag';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                // Aquí podrías permitir editar órdenes viejas, pero mejor lo dejamos solo lectura por seguridad
                Forms\Components\TextInput::make('id')
                    ->required()
                    ->maxLength(255),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('N° Orden')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Fecha')
                    ->date('d/m/Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('total')
                    ->label('Total')
                    ->money('ARS')
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->colors([
                        'warning' => 'Pending',
                        'success' => 'Completed',
                    ]),
                
                Tables\Columns\TextColumn::make('origin')
                    ->label('Origen')
                    ->badge()
                    ->formatStateUsing(function ($state) {
                        // Si ya es el Enum, lo usamos. Si es string, intentamos convertirlo.
                        $status = $state instanceof \App\Enums\Origin 
                            ? $state 
                            : \App\Enums\Origin::tryFrom($state);

                        return match ($status) {
                            \App\Enums\Origin::FISCAL => 'Blanco',
                            \App\Enums\Origin::INTERNAL => 'Negro',
                            default => 'Otro/Desconocido',
                        };
                    })
                    ->colors([
                        'success' => \App\Enums\Origin::FISCAL,
                        'danger' => \App\Enums\Origin::INTERNAL,
                    ]),
            ])
            ->filters([
                // Filtro para ver solo lo que deben
                Tables\Filters\SelectFilter::make('status')
                    ->label('Estado de Pago')
                    ->options([
                        'Pending' => 'Pendiente / Debe',
                        'Completed' => 'Pagado',
                    ]),
            ])
            ->headerActions([
                // No permitimos crear órdenes desde acá para obligar a usar el Wizard que descuenta stock
                // Tables\Actions\CreateAction::make(), 
            ])
            ->actions([
                // Botón para ver el PDF de esa compra vieja
                Tables\Actions\Action::make('print')
                    ->label('PDF')
                    ->icon('heroicon-o-printer')
                    ->url(fn (Order $record) => route('orders.pdf', $record))
                    ->openUrlInNewTab(),
            ])
            ->defaultSort('created_at', 'desc'); // Las más nuevas primero
    }
}