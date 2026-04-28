<?php

namespace App\Filament\Resources\LocalityResource\RelationManagers;

use App\Filament\Resources\ClientResource; 
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ClientsRelationManager extends RelationManager
{
    protected static string $relationship = 'clients';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('code')->label('Cód.')->searchable(),
                Tables\Columns\TextColumn::make('name')->label('Nombre Cliente')->searchable()->weight('bold'),
                Tables\Columns\TextColumn::make('address')->label('Dirección'),
                Tables\Columns\TextColumn::make('phone')->label('Teléfono'),
            ])
            ->actions([
                // Botón para ir directo a editar el cliente
                Tables\Actions\Action::make('edit_client')
                    ->label('Ir al Perfil')
                    ->icon('heroicon-o-user')
                    ->url(fn ($record) => ClientResource::getUrl('edit', ['record' => $record])),
                
                Tables\Actions\EditAction::make()->label('Editar Rápido'),
            ]);
    }
}