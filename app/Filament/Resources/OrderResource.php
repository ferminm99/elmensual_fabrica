<?php

namespace App\Filament\Resources;

use App\Enums\OrderStatus;
use App\Filament\Resources\OrderResource\Pages;
use App\Models\Article;
use App\Models\Order;
use App\Models\Sku;
use App\Models\Client;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Illuminate\Support\HtmlString;
use Filament\Support\Enums\ActionSize;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;
    protected static ?string $navigationIcon = 'heroicon-o-shopping-cart';
    protected static ?string $navigationGroup = 'Ventas';
    protected static ?string $modelLabel = 'Pedido';

    public static function form(Form $form): Form
    {
        $checkIsAssembled = function (Get $get, ?Order $record) {
            $status = $get('status') ?? $get('../../status') ?? $get('../../../../status');
            if (!$status && $record) {
                $status = $record->status;
                if ($status instanceof OrderStatus) $status = $status->value;
            }
            return in_array($status, [
                OrderStatus::Assembled->value, 
                OrderStatus::Checked->value, 
                OrderStatus::Dispatched->value, 
                OrderStatus::Delivered->value
            ]);
        };

        $autoAddRow = function (Get $get, Set $set) {
            $variants = $get('../../variants') ?? [];
            if (empty($variants)) return;
            $lastKey = array_key_last($variants);
            $lastItem = $variants[$lastKey];
            
            if (!empty($lastItem['color_id']) || !empty($lastItem['sku_id']) || ($lastItem['quantity'] ?? 0) > 0) {
                $variants[] = ['color_id' => null, 'sku_id' => null, 'size_id' => null, 'quantity' => null, 'packed_quantity' => null, 'unit_price' => null];
                $set('../../variants', $variants);
            }
        };

        return $form
            ->schema([
                Forms\Components\Section::make()
                    ->compact()
                    ->schema([
                        Forms\Components\Grid::make(4)->schema([
                            Forms\Components\Select::make('client_id')->label('Cliente')->options(Client::all()->pluck('name', 'id'))->searchable()->required(),
                            Forms\Components\Select::make('billing_type')->label('Facturación')->options(['fiscal' => 'Fiscal', 'informal' => 'Interno', 'mixed' => 'Mixto'])->default('fiscal')->required(),
                            Forms\Components\Select::make('status')->label('Estado')->options(OrderStatus::class)->default(OrderStatus::Draft)->required()->live(),
                            Forms\Components\DatePicker::make('order_date')->label('Fecha')->default(now())->required(),
                        ])
                    ]),

                Forms\Components\Section::make('Carga de Artículos')
                    ->compact()
                    ->headerActions([
                        Forms\Components\Actions\Action::make('matrix_load')
                            ->label('GENERAR MÚLTIPLES')
                            ->color('warning')
                            ->icon('heroicon-o-squares-2x2')
                            ->modalWidth('7xl')
                            ->form([
                                Forms\Components\Select::make('article_id')
                                    ->label('Artículo Base')
                                    ->options(Article::all()->mapWithKeys(fn($a) => [$a->id => "{$a->code} - {$a->name}"]))
                                    ->searchable()->live()->required()
                                    ->afterStateUpdated(function ($state, Set $set) {
                                        if (!$state) return $set('matrix', []);
                                        $colors = Sku::where('article_id', $state)
                                            ->join('colors', 'skus.color_id', '=', 'colors.id')
                                            ->select('colors.id', 'colors.name', 'colors.hex_code')
                                            ->distinct()->get();

                                        $set('matrix', $colors->map(fn($c) => [
                                            'color_id' => $c->id,
                                            'color_name' => $c->name,
                                            'color_hex' => $c->hex_code
                                        ])->toArray());
                                    }),

                                Forms\Components\Repeater::make('matrix')
                                    ->hiddenLabel()
                                    ->hidden(fn (Get $get) => !$get('article_id'))
                                    ->addable(false)->deletable(false)->reorderable(false)
                                    ->extraAttributes(['class' => 'matriz-erp-ultra-compacta'])
                                    ->schema(function (Get $get) {
                                        $articleId = $get('article_id');
                                        if (!$articleId) return [];
                                        
                                        $sizes = Sku::where('article_id', $articleId)
                                            ->join('sizes', 'skus.size_id', '=', 'sizes.id')
                                            ->select('sizes.id', 'sizes.name')->distinct()->orderBy('sizes.id')->get();
                                        $numTalles = count($sizes);

                                        return [
                                            Forms\Components\Placeholder::make('css_fix')
                                                ->hiddenLabel()
                                                ->content(new HtmlString("
                                                    <style>
                                                        .matriz-erp-ultra-compacta .fi-fo-repeater-item { padding: 0 !important; margin: 0 !important; border: none !important; background: transparent !important; }
                                                        .matriz-erp-ultra-compacta .fi-fo-repeater-item-content { padding: 0 !important; }
                                                        .matriz-erp-ultra-compacta .fi-fo-repeater-item-content > .grid {
                                                            display: grid !important;
                                                            grid-template-columns: 110px 30px repeat($numTalles, 1fr) !important;
                                                            gap: 1px !important; align-items: center !important;
                                                        }
                                                        .matriz-erp-ultra-compacta input { height: 26px !important; font-size: 11px !important; border-radius: 2px !important; text-align: center !important; font-weight: bold !important; padding: 0 !important; }
                                                        .btn-rayo-erp button { width: 22px !important; height: 22px !important; padding: 0 !important; }
                                                        .matriz-erp-ultra-compacta label { display: none !important; }
                                                    </style>
                                                "))->columnSpanFull(),
                                            
                                            Forms\Components\Placeholder::make('color_label')
                                                ->hiddenLabel()
                                                ->content(function ($state, Get $get) {
                                                    $name = $get('color_name') ?? 'Color';
                                                    $hex = $get('color_hex') ?? '#ccc';
                                                    return new HtmlString("
                                                        <div class='flex items-center gap-1.5 px-1 overflow-hidden'>
                                                            <div class='w-3 h-3 rounded-full flex-shrink-0' style='background-color:{$hex}; border: 1px solid rgba(0,0,0,0.1)'></div>
                                                            <span class='text-[10px] font-bold truncate uppercase text-gray-800'>{$name}</span>
                                                        </div>
                                                    ");
                                                }),

                                            Forms\Components\Actions::make([
                                                Forms\Components\Actions\Action::make('fill')
                                                    ->icon('heroicon-m-bolt')->color('info')->iconButton()
                                                    ->tooltip('Llenar fila')
                                                    ->extraAttributes(['class' => 'btn-rayo-erp'])
                                                    ->action(function (Set $set, Get $get) use ($sizes) {
                                                        $row = $get('');
                                                        $val = collect($row)->first(fn($v, $k) => str_starts_with($k, 'size_') && $v > 0) ?? 0;
                                                        foreach ($sizes as $s) { $set("size_{$s->id}", $val); }
                                                    })
                                            ]),

                                            ...collect($sizes)->map(fn($size) => 
                                                Forms\Components\TextInput::make("size_{$size->id}")
                                                    ->placeholder("{$size->name}")
                                                    ->numeric()
                                                    ->default(0)
                                                    ->extraInputAttributes(['title' => "Talle {$size->name}", 'onclick' => 'this.select()'])
                                            )->toArray(),

                                            Forms\Components\Hidden::make('color_id'),
                                        ];
                                    })->columns(1)
                            ])
                            ->action(function (array $data, Set $set, Get $get) {
                                $articleId = $data['article_id'];
                                $article = Article::find($articleId);
                                $newVariants = [];

                                foreach ($data['matrix'] as $row) {
                                    $colorId = $row['color_id'];
                                    foreach ($row as $key => $quantity) {
                                        if (str_starts_with($key, 'size_') && intval($quantity) > 0) {
                                            $sizeId = str_replace('size_', '', $key);
                                            $sku = Sku::where('article_id', $articleId)->where('color_id', $colorId)->where('size_id', $sizeId)->first();
                                            if ($sku) {
                                                $newVariants[] = [
                                                    'color_id' => $colorId,
                                                    'sku_id' => $sku->id,
                                                    'size_id' => $sizeId, 
                                                    'quantity' => intval($quantity),
                                                    'unit_price' => $article->base_cost
                                                ];
                                            }
                                        }
                                    }
                                }

                                $currentGroups = $get('article_groups') ?? [];
                                $groupIndex = collect($currentGroups)->search(fn($g) => ($g['article_id'] ?? null) == $articleId);
                                if ($groupIndex !== false) {
                                    $currentGroups[$groupIndex]['variants'] = array_merge($currentGroups[$groupIndex]['variants'] ?? [], $newVariants);
                                } else {
                                    $currentGroups[] = ['article_id' => $articleId, 'variants' => $newVariants];
                                }
                                $set('article_groups', $currentGroups);
                            }),
                    ])
                    ->schema([
                        Forms\Components\Repeater::make('article_groups')
                            ->live()
                            ->hiddenLabel()->addActionLabel('Agregar otro Artículo')->defaultItems(0)->collapsible()
                            ->itemLabel(function (array $state): ?HtmlString {
                                $articleId = $state['article_id'] ?? null;
                                if (!$articleId) return new HtmlString("<span class='text-gray-400 italic'>Nuevo Artículo...</span>");
                                $article = Article::find($articleId);
                                if (!$article) return null;
                                $variants = $state['variants'] ?? [];
                                $qty = collect($variants)->sum('quantity');
                                $total = collect($variants)->sum(fn($v) => (intval($v['quantity'] ?? 0) * floatval($v['unit_price'] ?? 0)));
                                return new HtmlString("<div class='flex justify-between items-center w-full px-1'><div class='flex items-center gap-2 overflow-hidden'><span class='font-black text-primary-600 text-lg'>{$article->code}</span><span class='font-medium text-gray-800 truncate'>{$article->name}</span></div><div class='flex items-center gap-2 text-sm whitespace-nowrap ml-2'><span class='bg-gray-100 text-gray-700 px-2 py-0.5 rounded font-bold'>{$qty} u.</span><span class='bg-green-50 text-green-700 px-2 py-0.5 rounded font-bold'>$ " . number_format($total, 0, ',', '.') . "</span></div></div>");
                            })
                            ->schema([
                                Forms\Components\Select::make('article_id')
                                    ->hiddenLabel()->placeholder('Buscar Artículo...')
                                    ->options(Article::all()->mapWithKeys(fn($a) => [$a->id => "{$a->code} - {$a->name}"]))
                                    ->searchable()->required()->live()
                                    ->afterStateUpdated(fn (Set $set) => $set('variants', [['color_id' => null, 'sku_id' => null, 'size_id' => null, 'quantity' => null]]))
                                    ->columnSpanFull(),

                                Forms\Components\Grid::make(12)
                                    ->extraAttributes(['class' => 'mb-1 border-b border-gray-200 pb-1'])
                                    ->schema(function (Get $get, ?Order $record) use ($checkIsAssembled) {
                                        $isAssembled = $checkIsAssembled($get, $record);

                                        return [
                                            Forms\Components\Placeholder::make('h1')->content('COLOR')->hiddenLabel()->extraAttributes(['class' => 'text-xs font-bold text-gray-500'])->columnSpan(3),
                                            Forms\Components\Placeholder::make('h2')->content('TALLE')->hiddenLabel()->extraAttributes(['class' => 'text-xs font-bold text-gray-500'])->columnSpan(2),
                                            
                                            // Columna Pedido
                                            Forms\Components\Placeholder::make('h3')
                                                ->content($isAssembled ? 'PEDIDO' : 'CANT.')
                                                ->hiddenLabel()
                                                ->extraAttributes(['class' => 'text-xs font-bold text-gray-500 text-center'])
                                                ->columnSpan($isAssembled ? 1 : 2),
                                                
                                            // Columna Armado (Visible si está armado)
                                            Forms\Components\Placeholder::make('h_pack')
                                                ->content('ARMADO')
                                                ->hiddenLabel()
                                                ->extraAttributes(['class' => 'text-xs font-bold text-blue-600 text-center'])
                                                ->columnSpan(1)
                                                ->visible($isAssembled),

                                            Forms\Components\Placeholder::make('h4')->content('PRECIO')->hiddenLabel()->extraAttributes(['class' => 'text-xs font-bold text-gray-500'])->columnSpan(3),
                                            Forms\Components\Placeholder::make('h5')->content('')->hiddenLabel()->columnSpan(1),
                                        ];
                                    }),

                                Forms\Components\Repeater::make('variants')
                                    ->live()
                                    ->hiddenLabel()->addable(false)->deletable(false)->reorderable(false)->collapsible(false)->defaultItems(1)
                                    ->extraAttributes(['class' => 'tabla-ultra-compacta'])
                                    ->schema(function (Get $get, ?Order $record) use ($autoAddRow, $checkIsAssembled) {
                                        
                                        $isAssembled = $checkIsAssembled($get, $record);

                                        return [
                                            Forms\Components\Placeholder::make('css_limpiador')
                                                ->content(new HtmlString('<style>.tabla-ultra-compacta .fi-fo-repeater-item { padding: 0 !important; margin: 0 !important; border: none !important; background: transparent !important; box-shadow: none !important; } .tabla-ultra-compacta .fi-fo-repeater-item-content { padding: 0 !important; } .tabla-ultra-compacta .grid { gap: 4px !important; }</style>'))
                                                ->columnSpanFull()->hiddenLabel(),

                                            Forms\Components\Hidden::make('size_id'),

                                            Forms\Components\Grid::make(12)
                                                ->extraAttributes(['class' => 'items-center !gap-x-1 !gap-y-0 !m-0 !p-0'])
                                                ->schema([
                                                    // SELECT DE COLOR
                                                    Forms\Components\Select::make('color_id')
                                                        ->hiddenLabel()->allowHtml()->searchable()
                                                        ->options(function (Get $get) {
                                                            $articleId = $get('../../article_id');
                                                            if (!$articleId) return [];
                                                            return Sku::where('article_id', $articleId)
                                                                ->join('colors', 'skus.color_id', '=', 'colors.id')
                                                                ->select('colors.id', 'colors.name', 'colors.hex_code')
                                                                ->distinct()->get()
                                                                ->mapWithKeys(fn ($c) => [$c->id => "<div class='flex items-center gap-2'><div class='w-3 h-3 rounded-full border border-gray-300' style='background-color:{$c->hex_code}'></div><span class='text-xs font-medium'>{$c->name}</span></div>"]);
                                                        })
                                                        ->live()
                                                        ->afterStateUpdated(fn(Get $get, Set $set) => $autoAddRow($get, $set))
                                                        ->columnSpan(3)->extraInputAttributes(['class' => '!h-7 !py-0 text-xs']),

                                                    // SELECT DE SKU
                                                    Forms\Components\Select::make('sku_id')
                                                        ->hiddenLabel()->searchable()
                                                        ->options(function (Get $get) {
                                                            $articleId = $get('../../article_id'); $colorId = $get('color_id');
                                                            if (!$articleId || !$colorId) return [];
                                                            return Sku::where('article_id', $articleId)->where('color_id', $colorId)->with('size')->get()
                                                                ->mapWithKeys(fn($s) => [$s->id => $s->size->name . ' (Stock: '.$s->stock_quantity.')']);
                                                        })
                                                        ->live()
                                                        ->afterStateUpdated(function ($state, Set $set, Get $get) use ($autoAddRow) {
                                                            $sku = Sku::find($state);
                                                            if($sku) {
                                                                $set('unit_price', $sku->article->base_cost);
                                                                $set('size_id', $sku->size_id);
                                                            }
                                                            $autoAddRow($get, $set);
                                                        })->columnSpan($isAssembled ? 2 : 3)->extraInputAttributes(['class' => '!h-7 !py-0 text-xs']),

                                                    // --- CANTIDAD PEDIDA (Siempre Editable) ---
                                                    Forms\Components\TextInput::make('quantity')
                                                        ->hiddenLabel()->numeric()->live()
                                                        ->dehydrated()
                                                        ->afterStateUpdated(fn(Get $get, Set $set) => $autoAddRow($get, $set))
                                                        ->columnSpan($isAssembled ? 1 : 2)
                                                        ->extraInputAttributes(['class' => '!h-7 !py-0 text-center font-bold text-sm']),

                                                    // --- CANTIDAD ARMADA (Solo lectura) ---
                                                    Forms\Components\TextInput::make('packed_quantity')
                                                        ->hiddenLabel()->numeric()->default(0)
                                                        ->columnSpan(1)
                                                        ->visible($isAssembled) 
                                                        ->disabled()            // SIEMPRE BLOQUEADO
                                                        ->dehydrated(false)
                                                        ->extraInputAttributes(['class' => '!h-7 !py-0 text-center font-bold text-sm bg-blue-50 border-blue-500 text-blue-700', 'title' => 'Cantidad Realmente Armada']),

                                                    Forms\Components\TextInput::make('unit_price')->hiddenLabel()->numeric()->disabled()->dehydrated()->columnSpan(3)->extraInputAttributes(['class' => '!h-7 !py-0 bg-gray-50/50 text-xs']),

                                                    Forms\Components\Actions::make([
                                                        Forms\Components\Actions\Action::make('delete')
                                                            ->icon('heroicon-m-trash')->color('danger')->iconButton()->size(ActionSize::Small)
                                                            ->action(function ($component, Forms\Set $set, Forms\Get $get) {
                                                                $uuid = (string) str($component->getContainer()->getStatePath())->afterLast('.');
                                                                $items = $get('../'); 
                                                                if (isset($items[$uuid])) { unset($items[$uuid]); $set('../', $items); Notification::make()->title('Eliminado')->success()->send(); }
                                                            }),
                                                    ])->columnSpan(1)->extraAttributes(['class' => 'flex justify-center items-center h-7']),
                                                ])
                                        ];
                                    })
                            ])->columns(1)
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            // 1. MODIFICAR LA CONSULTA POR DEFECTO PARA AGRUPAR
            ->modifyQueryUsing(function ($query) {
                // Esta lógica ordena primero por el ID del Padre (o el propio si es padre)
                // y luego por ID normal. Así quedan pegados.
                return $query->orderByRaw("COALESCE(parent_id, id) DESC, id ASC");
            })
            ->columns([
                Tables\Columns\TextColumn::make('id')
                ->label('#')
                    // 2. INDENTACIÓN VISUAL
                    ->formatStateUsing(function (Order $record) {
                        if ($record->parent_id) {
                            // Usamos un caracter especial para la "L"
                            return new HtmlString("<span style='margin-left: 20px; color: gray;'>└─</span> " . $record->id);
                        }
                        return $record->id;
                    })
                    ->color(fn (Order $record) => $record->parent_id ? 'gray' : null)
                    ->searchable(),
                Tables\Columns\TextColumn::make('client.name')->label('Cliente')->weight('bold')->searchable()
                    ->description(fn (Order $record) => ($record->client->locality?->name ?? 'Sin Localidad') . ' - ' . ($record->client->locality?->zone?->name ?? 'Sin Zona')),
                Tables\Columns\TextColumn::make('order_date')->date('d/m/Y')->label('Fecha'),
                
                // STATUS CON COLORES
                Tables\Columns\TextColumn::make('status')->label('Estado')->badge()
                    ->formatStateUsing(fn ($state) => $state->getLabel())
                    ->color(fn ($state) => match ($state) {
                        OrderStatus::Draft => 'gray',
                        OrderStatus::Processing => 'warning', // Naranja
                        OrderStatus::Assembled => 'info',     // Azul
                        OrderStatus::Checked => 'primary',    // Violeta
                        OrderStatus::Delivered => 'danger',   // Rojo
                        OrderStatus::Paid => 'success',       // Verde
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('total_amount')->label('Total')->money('ARS')->weight('bold'),

                // COLUMNA DE VINCULOS (FIXED)
                Tables\Columns\TextColumn::make('parent_id')
                    ->label('Vínculo')
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn ($state) => "Derivado de #$state")
                    ->placeholder('-'), // <-- FIX: Muestra '-' si es null en lugar de explotar
            ])
            ->defaultSort(null)
            ->filters([
                Tables\Filters\SelectFilter::make('zone')->label('Zona')->relationship('client.locality.zone', 'name'),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->visible(fn (Order $record) => $record->status === OrderStatus::Draft || $record->status === OrderStatus::Assembled),
                Tables\Actions\Action::make('revert_status')
                    ->label('Volver Estado')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (Order $order) => $order->status !== OrderStatus::Draft) // Solo si no es borrador
                    ->form([
                        Forms\Components\Textarea::make('reason')
                            ->label('Motivo del cambio')
                            ->required()
                    ])
                    ->action(function (Order $order, array $data) {
                        // 1. Guardar log
                        activity()
                            ->performedOn($order)
                            ->withProperties(['reason' => $data['reason'], 'previous_status' => $order->status])
                            ->log('status_reverted');

                        // 2. Cambiar estado (ejemplo: volver a Para Armar)
                        $order->update(['status' => OrderStatus::Processing]);
                        
                        Notification::make()->title('Estado revertido')->success()->send();
                    }),
                Tables\Actions\Action::make('send_to_warehouse')
                    ->label('Mandar a Depósito')
                    ->icon('heroicon-o-truck') // Cambié el icono a camión, queda mejor
                    ->color('warning')
                    ->requiresConfirmation()
                    // SOLO VISIBLE SI ES BORRADOR (Draft). Si ya es Processing o superior, desaparece.
                    ->visible(fn (Order $record) => $record->status === OrderStatus::Draft)
                    ->action(fn (Order $record) => $record->update(['status' => OrderStatus::Processing])),])
                            ->bulkActions([
                                Tables\Actions\BulkAction::make('bulk_warehouse')->label('Mandar seleccionados a Depósito')->icon('heroicon-o-truck')->color('warning')->action(function ($records) { $records->each(fn ($r) => $r->update(['status' => OrderStatus::Processing])); Notification::make()->title('Enviados')->success()->send(); })->requiresConfirmation()
                                    ->modalDescription(function ($records) { return "Vas a mandar {$records->count()} pedidos al armador. Asegurate de que no falte ninguno de la zona elegida."; }),
                            ]);
    }

    public static function getRelations(): array { return []; }
    public static function getPages(): array { return [ 'index' => Pages\ListOrders::route('/'), 'create' => Pages\CreateOrder::route('/create'), 'edit' => Pages\EditOrder::route('/{record}/edit'), ]; }
}