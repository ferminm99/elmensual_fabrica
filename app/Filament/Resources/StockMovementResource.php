<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StockMovementResource\Pages;
use App\Models\StockMovement;
use App\Models\Sku;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class StockMovementResource extends Resource
{
    protected static ?string $model = StockMovement::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrows-right-left';
    
    // Etiquetas en Español
    protected static ?string $modelLabel = 'Movimiento de Stock';
    protected static ?string $pluralModelLabel = 'Ajustes de Inventario'; // <--- ERROR CORREGIDO (saqué el punto)
    protected static ?string $navigationGroup = 'Compras & Stock';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Detalle del Ajuste')
                    ->schema([
                        // Buscador Inteligente: Muestra Artículo + Talle + Color + Stock Actual
                        Forms\Components\Select::make('sku_id')
                            ->label('Producto')
                            ->options(Sku::with(['article', 'size', 'color'])->get()->mapWithKeys(function ($sku) {
                                return [$sku->id => "{$sku->article->name} - {$sku->size->name} / {$sku->color->name} (Stock Actual: {$sku->stock_quantity})"];
                            }))
                            ->searchable()
                            ->required()
                            ->columnSpanFull(),

                        Forms\Components\Select::make('type')
                            ->label('Tipo de Movimiento')
                            ->options([
                                'Entry' => 'Ingreso (Suma Stock)',
                                'Exit' => 'Egreso (Resta Stock)',
                            ])
                            ->required(),

                        Forms\Components\Select::make('reason')
                            ->label('Motivo')
                            ->options([
                                'Robo/Hurto' => 'Robo / Hurto',
                                'Pérdida/Daño' => 'Pérdida / Daño',
                                'Regalo/Marketing' => 'Regalo / Marketing',
                                'Conteo Inicial' => 'Ajuste por Conteo',
                                'Devolución' => 'Devolución de Cliente',
                            ])
                            ->required(),

                        Forms\Components\TextInput::make('quantity')
                            ->label('Cantidad')
                            ->numeric()
                            ->minValue(1)
                            ->required(),
                        
                        // Guardamos el usuario automáticamente (oculto)
                        Forms\Components\Hidden::make('user_id')
                            ->default(fn () => Auth::id()),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i'),

                Tables\Columns\TextColumn::make('sku.article.name')
                    ->label('Artículo')
                    ->searchable(),

                Tables\Columns\TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->colors([
                        'success' => 'Entry',
                        'danger' => 'Exit',
                    ])
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'Entry' => 'Ingreso',
                        'Exit' => 'Salida',
                        default => $state,
                    }),

                Tables\Columns\TextColumn::make('quantity')
                    ->label('Cant.')
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('reason')
                    ->label('Motivo'),
                
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Responsable')
                    ->toggleable(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStockMovements::route('/'),
            'create' => Pages\CreateStockMovement::route('/create'),
        ];
    }
}