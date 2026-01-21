<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductionOrderResource\Pages;
use App\Filament\Resources\ProductionOrderResource\RelationManagers;
use App\Models\ProductionOrder;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ProductionOrderResource extends Resource
{
    protected static ?string $model = ProductionOrder::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?string $navigationGroup = 'Producción';
    protected static ?string $modelLabel = 'Orden de Producción';
    protected static ?string $pluralModelLabel = 'Órdenes de Producción';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Group::make()->schema([
                    Forms\Components\Section::make('Fase 1: Datos del Corte')
                        ->schema([
                            Forms\Components\TextInput::make('code')
                                ->default('CORTE-' . rand(1000, 9999))
                                ->readOnly(),
                            
                            // SELECTOR DE ESTADO (Controla el flujo)
                            Forms\Components\Select::make('status')
                                ->label('Estado del Proceso')
                                ->options([
                                    'pendiente' => '1. Recién Cortado (Pendiente)',
                                    'en_proceso' => '2. En Confección (Taller)',
                                    'finalizado' => '3. Finalizado (Control de Stock)',
                                ])
                                ->default('pendiente')
                                ->live() // IMPORTANTE: Para mostrar/ocultar secciones
                                ->required(),

                            Forms\Components\Select::make('raw_material_id')
                                ->label('Insumo Utilizado')
                                ->relationship('rawMaterial', 'name')
                                ->live()
                                ->required(),
                                
                            Forms\Components\TextInput::make('usage_quantity')
                                ->label('Cantidad Tela Usada')
                                ->numeric()
                                ->required(),

                            Forms\Components\Select::make('article_id')
                                ->label('Artículo Base')
                                ->options(\App\Models\Article::all()->pluck('name', 'id'))
                                ->live()
                                ->required(),
                        ])->columns(2),
                ]),

                // ESTA SECCIÓN SOLO APARECE SI EL ESTADO ES "FINALIZADO"
                Forms\Components\Section::make('Fase 2: Ingreso de Prendas Terminadas')
                    ->description('Cargar esto solo cuando vuelvan del taller')
                    ->schema([
                        Forms\Components\Repeater::make('items')
                            ->relationship()
                            ->schema([
                                Forms\Components\Select::make('sku_id')
                                    ->label('Variante')
                                    ->options(function (Forms\Get $get) {
                                        // TU LÓGICA DE FILTRADO (Ya la tenías, la copio igual)
                                        $materialId = $get('../../raw_material_id');
                                        $articleId = $get('../../article_id');
                                        if (!$materialId || !$articleId) return [];
                                        
                                        $material = \App\Models\RawMaterial::find($materialId);
                                        $colorId = $material?->color_id;

                                        return \App\Models\Sku::where('article_id', $articleId)
                                            ->where('color_id', $colorId)
                                            ->get()
                                            ->mapWithKeys(fn ($sku) => [$sku->id => $sku->size->name . ' (' . $sku->color->name . ')']);
                                    })
                                    ->required(),
                                
                                Forms\Components\TextInput::make('quantity')
                                    ->label('Cantidad Real')
                                    ->numeric()
                                    ->required(),
                            ])
                            ->columns(2)
                    ])
                    // AQUÍ ESTÁ EL TRUCO VISUAL:
                    ->visible(fn (Forms\Get $get) => $get('status') === 'finalizado'), 
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc') // Ordenar por más nuevo primero
            ->columns([
                // 1. CÓDIGO DEL CORTE
                Tables\Columns\TextColumn::make('code')
                    ->label('Corte #')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->copyable(),

                // 2. ESTADO (Visual)
                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pendiente' => 'gray',
                        'en_proceso' => 'warning',   // Naranja para Taller
                        'finalizado' => 'success',   // Verde para Terminado
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pendiente' => 'Pendiente',
                        'en_proceso' => 'En Confección',
                        'finalizado' => 'Finalizado',
                        default => $state,
                    }),

                // 3. TELA / INSUMO
                Tables\Columns\TextColumn::make('rawMaterial.name')
                    ->label('Insumo')
                    ->searchable()
                    ->limit(20),

                // 4. CANTIDAD USADA
                Tables\Columns\TextColumn::make('usage_quantity')
                    ->label('Consumo')
                    ->numeric()
                    ->suffix(' mts/kg'),

                // 5. ARTÍCULO (Con Tooltip Mágico)
                Tables\Columns\TextColumn::make('article.code')
                    ->label('Art. Código')
                    ->searchable()
                    ->color('primary')
                    ->tooltip(fn ($record) => $record->article->name), // <--- AQUÍ ESTÁ EL TOOLTIP

                // 6. RESPONSABLE (Usuario)
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Resp.')
                    ->icon('heroicon-m-user')
                    ->toggleable(),

                // 7. FECHA
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                // Filtro rápido para ver solo lo que está en taller
                Tables\Filters\SelectFilter::make('status')
                    ->label('Filtrar por Estado')
                    ->options([
                        'pendiente' => 'Pendiente',
                        'en_proceso' => 'En Confección',
                        'finalizado' => 'Finalizado',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
    
    public static function getRelations(): array
    {
        return [
            // Como estamos "en casa", usamos el prefijo RelationManagers que se definió arriba
            RelationManagers\ActivitiesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProductionOrders::route('/'),
            'create' => Pages\CreateProductionOrder::route('/create'),
            'edit' => Pages\EditProductionOrder::route('/{record}/edit'),
        ];
    }
}