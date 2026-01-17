<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RawMaterialResource\Pages;
use App\Models\RawMaterial;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class RawMaterialResource extends Resource
{
    protected static ?string $model = RawMaterial::class;
    
    // Icono de "Caja" o "Cubo"
    protected static ?string $navigationIcon = 'heroicon-o-cube'; 
    
    // Etiquetas en Español
    protected static ?string $modelLabel = 'Materia Prima';
    protected static ?string $pluralModelLabel = 'Materias Primas';
    protected static ?string $navigationGroup = 'Producción';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Nombre del Insumo')
                    ->required()
                    ->maxLength(255),
                
                Forms\Components\Select::make('unit')
                    ->label('Unidad de Medida')
                    ->options([
                        'Meters' => 'Metros',
                        'Kilos' => 'Kilos',
                        'Units' => 'Unidades',
                    ])
                    ->required(),

                Forms\Components\TextInput::make('stock_quantity')
                    ->label('Stock Actual')
                    ->numeric()
                    ->default(0),

                Forms\Components\TextInput::make('avg_cost')
                    ->label('Costo Promedio')
                    ->prefix('$')
                    ->numeric()
                    ->default(0),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Insumo')
                    ->searchable(),
                Tables\Columns\TextColumn::make('stock_quantity')
                    ->label('Stock')
                    ->numeric(decimalPlaces: 2),
                Tables\Columns\TextColumn::make('unit')
                    ->label('Unidad')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'Meters' => 'Metros',
                        'Kilos' => 'Kilos',
                        'Units' => 'Unidades',
                        default => $state,
                    }),
                Tables\Columns\TextColumn::make('avg_cost')
                    ->label('Costo')
                    ->money('ARS'),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRawMaterials::route('/'),
            'create' => Pages\CreateRawMaterial::route('/create'),
            'edit' => Pages\EditRawMaterial::route('/{record}/edit'),
        ];
    }
}