<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LocalityResource\Pages;
use App\Filament\Resources\LocalityResource\RelationManagers;
use App\Models\Locality;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

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
            Forms\Components\TextInput::make('code')->label('Código Loc. (Ej: 0088)')->required(),
            Forms\Components\TextInput::make('name')->label('Nombre Localidad')->required(),
        ]);
    }

    public static function table(Table $table): Table {
        return $table->columns([
            Tables\Columns\TextColumn::make('zone.name')->label('Zona')->sortable(),
            Tables\Columns\TextColumn::make('code')->label('Cód'),
            Tables\Columns\TextColumn::make('name')->label('Localidad')->searchable(),
        ]);
    }

    public static function getRelations(): array
    {
        return [
            //
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