<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductionOrderResource\Pages;
use App\Filament\Resources\ProductionOrderResource\RelationManagers;
use App\Models\ProductionOrder;
use App\Models\RawMaterial;
use App\Models\Article;
use App\Models\Sku;
use App\Models\Color;
use App\Models\Recipe;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;
use Filament\Notifications\Notification;
use Illuminate\Support\HtmlString;

// Función para calcular las prendas esperadas
function calcularPrendasEsperadas(Set $set, Get $get) {
    $materialId = $get('raw_material_id');
    $articleId = $get('article_id');
    $usage = (float) $get('usage_quantity');

    if ($materialId && $articleId && $usage > 0) {
        $recipe = Recipe::where('article_id', $articleId)
            ->where('raw_material_id', $materialId)
            ->first();
            
        // Ajustá 'quantity' según cómo se llame en tu tabla (puede ser consumption)
        $consumo = $recipe?->quantity ?? 0; 
        
        if ($consumo > 0) {
            $set('expected_quantity', floor($usage / $consumo));
        }
    }
}

class ProductionOrderResource extends Resource
{
    protected static ?string $model = ProductionOrder::class;

    protected static ?string $navigationIcon = 'heroicon-o-scissors';
    protected static ?string $navigationGroup = 'Producción';
    protected static ?string $modelLabel = 'Orden de Producción';
    protected static ?string $pluralModelLabel = 'Órdenes de Producción';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Group::make()->schema([
                    
                    // --- FASE 1: CABECERA ---
                    Forms\Components\Section::make('Fase 1: Configuración del Corte')
                        ->schema([
                            Forms\Components\Grid::make(3)->schema([
                                Forms\Components\TextInput::make('code')
                                    ->label('Código')
                                    ->default('CORTE-' . rand(1000, 9999))
                                    ->readOnly(),

                                Forms\Components\Select::make('status')
                                    ->label('Estado')
                                    ->options([
                                        'draft' => '1. Planificando (Borrador)',
                                        'en_proceso' => '2. En Taller (Corte/Confección)',
                                        'finalizado' => '3. Finalizado (Stock Ingresado)',
                                    ])
                                    ->default('draft')
                                    ->live()
                                    ->required()
                                    // BLOQUEAMOS PONER "FINALIZADO" A MANO
                                    ->disableOptionWhen(fn (string $value, ?ProductionOrder $record) => $value === 'finalizado' && ($record ? $record->status !== 'finalizado' : true))
                                    ->helperText(fn (Get $get, ?ProductionOrder $record) => 
                                        ($record && $record->status === 'en_proceso') 
                                        ? 'Para finalizar y cargar el stock, usá el botón verde "Finalizar e Ingresar Stock" en la parte superior.' 
                                        : ''
                                    ),

                                Forms\Components\DatePicker::make('created_at')
                                    ->label('Fecha')
                                    ->default(now())
                                    ->required(),
                            ]),
                            
                            // EL CÓDIGO Y NOMBRE DEL ARTÍCULO
                            Forms\Components\Select::make('article_ids')
                                ->label('Artículos a Fabricar')
                                ->multiple()
                                ->options(Article::all()->mapWithKeys(fn($a) => [$a->id => "{$a->code} - {$a->name}"]))
                                ->searchable()
                                ->live()
                                ->required()
                                ->columnSpanFull(),
                        ]),

                    // --- FASE 1.5: REPEATER DE INSUMOS ---
                    Forms\Components\Section::make('Materia Prima (Telas)')
                        ->description('Cargá los rollos/telas y colores exactos que se van a usar en esta tizada.')
                        ->schema([
                            Forms\Components\Repeater::make('used_materials')
                                ->label('')
                                ->schema([
                                    Forms\Components\Select::make('raw_material_id')
                                        ->label('Insumo')
                                        ->options(RawMaterial::pluck('name', 'id'))
                                        ->searchable()
                                        ->required(),

                                    Forms\Components\Select::make('color_id')
                                        ->label('Color de la Tela')
                                        ->options(Color::pluck('name', 'id'))
                                        ->searchable()
                                        ->required(),

                                    Forms\Components\TextInput::make('usage_quantity')
                                        ->label('Metros/Kg a usar')
                                        ->numeric()
                                        ->required(),
                                ])
                                ->columns(3)
                                ->defaultItems(1)
                                ->live(debounce: 500)
                        ]),

                    // --- FASE 2: GENERADOR DE MATRIZ (PLANIFICACIÓN) ---
                    Forms\Components\Section::make('Fase 2: Cantidades a Cortar (Esperadas)')
                        ->description('Ingresá las cantidades esperadas por talle. Usá el rayo ⚡ para repetir el valor.')
                        ->visible(fn (Get $get) => in_array($get('status'), ['draft', 'en_proceso']))
                        ->headerActions([
                            Forms\Components\Actions\Action::make('generar_matriz')
                                ->label('Generar Matriz de Corte')
                                ->icon('heroicon-o-table-cells')
                                ->color('primary')
                                ->action(function (Set $set, Get $get) {
                                    $articleIds = $get('article_ids') ?? [];
                                    $usedMaterials = $get('used_materials') ?? [];

                                    if (empty($articleIds) || empty($usedMaterials)) {
                                        Notification::make()->warning()->title('Faltan datos')->body('Seleccioná al menos un artículo y un insumo.')->send();
                                        return;
                                    }

                                    $colorIds = collect($usedMaterials)->pluck('color_id')->filter()->unique()->toArray();
                                    $colors = Color::whereIn('id', $colorIds)->get();

                                    $groups = [];
                                    $mensajesSugeridos = [];

                                    foreach ($articleIds as $articleId) {
                                        $article = Article::find($articleId);
                                        $matrix = [];
                                        
                                        $receta = Recipe::where('article_id', $articleId)->first();
                                        $consumoPorPrenda = $receta ? $receta->quantity_required : 0;
                                        $totalTela = collect($usedMaterials)->sum('usage_quantity');
                                        
                                        if ($consumoPorPrenda > 0 && $totalTela > 0) {
                                            $esperadas = floor($totalTela / $consumoPorPrenda);
                                            $mensajesSugeridos[] = "{$article->code}: Se estiman ~{$esperadas} prendas.";
                                        }
                                        
                                        foreach ($colors as $color) {
                                            $matrix[uniqid()] = [
                                                'color_id' => $color->id,
                                                'color_name' => $color->name,
                                                'color_hex' => $color->hex_code ?? '#cccccc',
                                            ];
                                        }

                                        $groups[uniqid()] = [
                                            'article_id' => $article->id,
                                            'matrix' => $matrix,
                                        ];
                                    }

                                    $set('article_groups', $groups);

                                    if (!empty($mensajesSugeridos)) {
                                        Notification::make()->info()->title('Cálculo de Rinde')->body(implode(' | ', $mensajesSugeridos))->send();
                                    } else {
                                        Notification::make()->success()->title('Matriz Generada')->send();
                                    }
                                })
                        ])
                        ->schema([
                            // ACÁ LLAMAMOS A TU ARCHIVO BLADE EXISTENTE DE LA MATRIZ
                            Forms\Components\ViewField::make('article_groups')
                                ->view('filament.components.order-matrix-editor')
                                ->columnSpanFull()
                                ->reactive()
                                ->registerActions([
                                    Forms\Components\Actions\Action::make('removeArticle')
                                        ->action(function (array $arguments, Set $set, Get $get) {
                                            $current = $get('article_groups'); 
                                            unset($current[$arguments['groupKey']]); 
                                            $set('article_groups', $current);
                                        }),
                                        
                                    // ACCIÓN DEL BOLT (RAYO) FUNCIONANDO
                                    Forms\Components\Actions\Action::make('fillRow')
                                        ->action(function (array $arguments, Set $set, Get $get) {
                                            $uuid = $arguments['uuid']; 
                                            $gk = $arguments['groupKey'];
                                            
                                            $row = $get("article_groups.{$gk}.matrix.{$uuid}");
                                            $val = 0;
                                            
                                            foreach ($row as $k => $v) { 
                                                if (str_starts_with($k, 'qty_') && (int)$v > 0) { 
                                                    $val = (int)$v; 
                                                    break; 
                                                } 
                                            }
                                            
                                            $articleId = $get("article_groups.{$gk}.article_id");
                                            $sizes = Sku::where('article_id', $articleId)->pluck('size_id')->unique();
                                            
                                            foreach ($sizes as $sId) { 
                                                $set("article_groups.{$gk}.matrix.{$uuid}.qty_{$sId}", $val); 
                                            }
                                        }),
                                ]),
                        ]),

                    // --- FASE 3: LECTURA DE OBSERVACIONES (FINALIZACIÓN) ---
                    Forms\Components\Section::make('Cierre y Observaciones (Sólo Lectura)')
                        ->visible(fn (Get $get) => $get('status') === 'finalizado')
                        ->schema([
                            Forms\Components\Textarea::make('observations')
                                ->label('Observaciones guardadas')
                                ->disabled()
                                ->columnSpanFull(),
                        ])
                ])->columnSpanFull()
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('code')->label('Corte #')->searchable()->sortable()->weight('bold')->copyable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'draft' => 'gray',
                        'en_proceso' => 'warning',
                        'finalizado' => 'success',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'draft' => 'Borrador',
                        'en_proceso' => 'En Taller',
                        'finalizado' => 'Finalizado',
                        default => $state,
                    }),
                Tables\Columns\TextColumn::make('created_at')->label('Fecha')->date('d/m/Y')->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Estado')
                    ->options([
                        'draft' => 'Planificando',
                        'en_proceso' => 'En Taller',
                        'finalizado' => 'Finalizado',
                    ]),
            ])
            ->actions([
                // ENVIAR AL TALLER DESDE LA TABLA
                Tables\Actions\Action::make('enviar_taller')
                    ->label('Mandar a Taller')
                    ->icon('heroicon-o-truck')
                    ->color('warning')
                    ->visible(fn ($record) => $record->status === 'draft')
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        $record->update(['status' => 'en_proceso']);
                        Notification::make()->success()->title('Orden enviada al taller')->send();
                    }),

                // EL GRAN CIERRE CON LA MATRIZ DE RENDIMIENTO
                Tables\Actions\Action::make('finalizar_corte')
                    ->label('Finalizar e Ingresar Stock')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->visible(fn ($record) => $record->status === 'en_proceso')
                    ->modalWidth('7xl')
                    ->closeModalByClickingAway(false)
                    ->mountUsing(function (Forms\ComponentContainer $form, $record) {
                        // Llenamos el modal con la matriz EXACTA que se guardó al planificar
                        $form->fill([
                            'article_groups' => $record->article_groups,
                            'observations' => $record->observations,
                            'status' => 'draft', // Engañamos a tu vista blade para habilitar edición
                        ]);
                    })
                    ->form(fn (ProductionOrder $record) => [ // ACA ESTA LA CLAVE: Inyectamos el $record
                        Forms\Components\Hidden::make('status'),
                        
                        Forms\Components\ViewField::make('article_groups')
                            ->view('filament.components.production-matrix-editor')
                            ->viewData(['original_groups' => $record->article_groups]) // Le mandamos la BD original a la vista
                            ->columnSpanFull()
                            ->reactive(),

                        Forms\Components\Textarea::make('observations')
                            ->label('Observaciones / Mermas')
                            ->placeholder('Ej: Faltaron 3 del talle S negro porque la tela vino fallada.')
                            ->columnSpanFull(),
                    ])
                    // ACÁ INYECTAMOS $action PARA PODER FRENAR EL MODAL
                    ->action(function ($record, array $data, Tables\Actions\Action $action) {
                        
                        // 1. VALIDACIÓN ESTRICTA DE SERVIDOR (Evitar celdas vacías)
                        $originalGroups = $record->article_groups ?? [];
                        foreach ($data['article_groups'] ?? [] as $gk => $groupData) {
                            foreach ($groupData['matrix'] ?? [] as $rk => $rowData) {
                                foreach ($rowData as $field => $qty) {
                                    if (str_starts_with($field, 'qty_')) {
                                        $originalQty = $originalGroups[$gk]['matrix'][$rk][$field] ?? 0;
                                        
                                        // Si antes había un número > 0 y ahora borraron el contenido ("")
                                        if ((int)$originalQty > 0 && $qty === "") {
                                            Notification::make()
                                                ->danger()
                                                ->title('Faltan cantidades')
                                                ->body("Borraste una celda que esperaba {$originalQty} prendas. Si no ingresó ninguna, debes poner '0'.")
                                                ->persistent()
                                                ->send();
                                            
                                            $action->halt(); // Frena el guardado y deja la ventana abierta
                                        }
                                    }
                                }
                            }
                        }

                        // 2. SI TODO ESTÁ OK, GUARDAMOS EN LA BASE DE DATOS
                        DB::transaction(function () use ($record, $data) {
                            $totalIngresado = 0;
                            
                            foreach ($data['article_groups'] ?? [] as $groupKey => $groupData) {
                                $articleId = $groupData['article_id'];
                                
                                foreach ($groupData['matrix'] ?? [] as $rowKey => $rowData) {
                                    $colorId = $rowData['color_id'];
                                    
                                    foreach ($rowData as $field => $qty) {
                                        // Solo guardamos si no está vacío y es mayor a 0
                                        if (str_starts_with($field, 'qty_') && $qty !== "" && (int)$qty > 0) {
                                            $sizeId = str_replace('qty_', '', $field);
                                            $cantidad = (int)$qty;

                                            $sku = Sku::where('article_id', $articleId)
                                                ->where('color_id', $colorId)
                                                ->where('size_id', $sizeId)
                                                ->first();

                                            if ($sku) {
                                                // A. Historial de prendas fabricadas
                                                $record->items()->create([
                                                    'sku_id' => $sku->id,
                                                    'quantity' => $cantidad,
                                                ]);

                                                // B. ¡CORRECCIÓN ACÁ! Sumamos a 'stock_quantity'
                                                $sku->increment('stock_quantity', $cantidad);
                                                $totalIngresado += $cantidad;
                                            }
                                        }
                                    }
                                }
                            }

                            // 3. Pisamos la matriz planificada con los números reales
                            $record->update([
                                'status' => 'finalizado',
                                'article_groups' => $data['article_groups'],
                                'observations' => $data['observations'] ?? null,
                            ]);
                            
                            Notification::make()->success()->title("Corte finalizado. Ingresaron {$totalIngresado} prendas.")->send();
                        });
                    }),

                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }
    
    public static function getRelations(): array
    {
        return [
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