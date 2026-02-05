<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SalesmanResource\Pages;
use App\Filament\Resources\SalesmanResource\RelationManagers;
use App\Models\Salesman;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class SalesmanResource extends Resource
{
    protected static ?string $model = Salesman::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?string $navigationGroup = 'Ventas'; 
    protected static ?string $modelLabel = 'Viajante';
    
    // app/Filament/Resources/SalesmanResource.php

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Datos del Viajante')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nombre Completo')
                            ->required(),
                        
                        Forms\Components\TextInput::make('email')
                            ->email()
                            ->unique(ignoreRecord: true),

                        Forms\Components\TextInput::make('phone')
                            ->label('Teléfono'),

                        // ESTO ES CLAVE: Selector de Zonas
                        Forms\Components\Select::make('zones')
                            ->label('Zonas Asignadas')
                            ->relationship('zones', 'name') // Usa la relación del modelo
                            ->multiple() // Permite elegir varias
                            ->preload()
                            ->searchable()
                            ->columnSpanFull(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),

                // Muestra las zonas como etiquetas
                Tables\Columns\TextColumn::make('zones.name')
                    ->label('Zonas')
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('clients_count')
                    ->label('Cant. Clientes')
                    ->counts('clients'), // Cuenta los clientes asociados
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
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
            'index' => Pages\ListSalesmen::route('/'),
            'create' => Pages\CreateSalesman::route('/create'),
            'edit' => Pages\EditSalesman::route('/{record}/edit'),
        ];
    }
}