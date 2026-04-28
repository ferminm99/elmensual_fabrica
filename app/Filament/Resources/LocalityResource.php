<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LocalityResource\Pages;
use App\Filament\Resources\LocalityResource\RelationManagers\ClientsRelationManager;
use App\Models\Locality;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Dotswan\MapPicker\Fields\Map;

class LocalityResource extends Resource
{
    protected static ?string $model = Locality::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?string $modelLabel = 'Localidad';
    protected static ?string $pluralModelLabel = 'Localidades';
    protected static ?string $navigationGroup = 'Ventas';

    public static function form(Form $form): Form {
        return $form->schema([
            Forms\Components\Select::make('zone_id')
                ->label('Zona')
                ->relationship('zone', 'name')
                ->required(),
            Forms\Components\TextInput::make('code')
                ->label('Código Loc. (Ej: 0088)')
                ->required(),
            Forms\Components\TextInput::make('name')
                ->label('Nombre Localidad')
                ->required(),
                
            Forms\Components\TextInput::make('client_capacity')
                ->label('Límite de Clientes')
                ->numeric()
                ->default(5)
                ->required(),
            
            Forms\Components\Hidden::make('lat'),
            Forms\Components\Hidden::make('lng'),
            
            Map::make('location')
                ->label('Ubicación en el Mapa')
                ->columnSpanFull()
                ->defaultLocation(latitude: -34.9214, longitude: -57.9545)
                ->afterStateHydrated(function (Forms\Set $set, $record) {
                    if ($record && $record->lat && $record->lng) {
                        $set('location', ['lat' => $record->lat, 'lng' => $record->lng]);
                    }
                })
                ->afterStateUpdated(function (Forms\Get $get, Forms\Set $set, string|array|null $state): void {
                    if (is_array($state)) {
                        $set('lat', $state['lat']);
                        $set('lng', $state['lng']);
                    }
                })
                ->showMarker()
                ->markerColor('#22c55e')
                ->showFullscreenControl()
                ->showZoomControl()
                ->draggable(true) 
                ->clickable(true), 
        ]);
    }

    public static function table(Table $table): Table {
        return $table->columns([
            Tables\Columns\TextColumn::make('zone.name')
                ->label('Zona')
                ->sortable(),
            Tables\Columns\TextColumn::make('code')
                ->label('Cód'),
            Tables\Columns\TextColumn::make('name')
                ->label('Localidad')
                ->searchable(),
                
            Tables\Columns\TextColumn::make('client_capacity')
                ->label('Límite Clientes')
                ->sortable()
                ->badge() // Le da un diseño tipo "etiqueta" para que resalte
                ->color('info'),
        ]);
    }

    public static function getRelations(): array
    {
        return [
            ClientsRelationManager::class, // <-- Solo el nombre de la clase
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLocalities::route('/'),
            'create' => Pages\CreateLocality::route('/create'),
            'edit' => Pages\EditLocality::route('/{record}/edit'),
        ];
    }
}