<?php

namespace App\Filament\Resources\RawMaterialResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class StocksRelationManager extends RelationManager
{
    protected static string $relationship = 'stocks';

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
            Tables\Columns\TextColumn::make('color.name')
                ->label('Color')
                ->badge()
                ->color(fn ($record) => \Filament\Support\Colors\Color::hex($record->color->hex_code ?? '#cccccc')),
            
            Tables\Columns\TextInputColumn::make('quantity') // ¡Editable directo en la tabla!
                ->label('Cantidad (Stock)')
                ->type('number')
                ->rules(['numeric', 'min:0']),

            Tables\Columns\TextColumn::make('location')
                ->label('Ubicación')
                ->icon('heroicon-m-map-pin'),
        ])
        ->headerActions([
            Tables\Actions\CreateAction::make()
                ->label('Agregar Color')
                ->form([
                    Forms\Components\Select::make('color_id')
                        ->relationship('color', 'name')
                        ->required()
                        ->searchable()
                        ->preload(),
                    Forms\Components\TextInput::make('quantity')
                        ->label('Cantidad Inicial')
                        ->numeric()
                        ->default(0)
                        ->required(),
                    Forms\Components\TextInput::make('location')
                        ->label('Ubicación en Depósito'),
                ]),
        ])
        ->actions([
            Tables\Actions\DeleteAction::make(),
        ]);
    }
}