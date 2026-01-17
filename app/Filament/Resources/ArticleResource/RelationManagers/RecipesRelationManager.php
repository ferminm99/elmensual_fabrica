<?php

namespace App\Filament\Resources\ArticleResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use App\Models\RawMaterial;

class RecipesRelationManager extends RelationManager
{
    protected static string $relationship = 'recipes';
    
    // Título de la sección
    protected static ?string $title = 'Ficha Técnica / Receta';
    
    // Icono
    protected static ?string $icon = 'heroicon-o-clipboard-document-list';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('raw_material_id')
                    ->label('Materia Prima / Insumo')
                    ->options(RawMaterial::all()->pluck('name', 'id'))
                    ->searchable()
                    ->required(),
                
                Forms\Components\TextInput::make('quantity_required')
                    ->label('Cantidad Necesaria')
                    ->helperText('¿Cuánto consume 1 unidad de este artículo?')
                    ->numeric()
                    ->required(),

                Forms\Components\TextInput::make('waste_percent')
                    ->label('% Desperdicio')
                    ->numeric()
                    ->default(0)
                    ->suffix('%'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                Tables\Columns\TextColumn::make('rawMaterial.name')
                    ->label('Insumo'),
                
                Tables\Columns\TextColumn::make('quantity_required')
                    ->label('Consumo p/Unidad')
                    ->suffix(fn ($record) => ' ' . ($record->rawMaterial->unit === 'Kilos' ? 'kg' : 'un')),

                Tables\Columns\TextColumn::make('waste_percent')
                    ->label('Desperdicio')
                    ->suffix('%'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Agregar Insumo')
                    ->modalHeading('Agregar Insumo a la Receta'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }
}