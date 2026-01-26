<?php

namespace App\Filament\Resources;

use App\Enums\OrderStatus;
use App\Filament\Resources\OrderResource\Pages;
use App\Models\Article;
use App\Models\Order;
use App\Models\Sku;
use App\Models\Client;
use App\Models\Zone;      // Importante para el filtro
use App\Models\Locality;  // Importante para el filtro
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
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Collection; // Importante para la acción masiva
use Illuminate\Support\Facades\DB;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;
    protected static ?string $navigationIcon = 'heroicon-o-shopping-cart';
    protected static ?string $navigationGroup = 'Ventas';
    protected static ?string $modelLabel = 'Pedido';

    public static function form(Form $form): Form
    {
        // Función para detectar si está armado
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
                // --- CABECERA Y ESTADO ---
                Forms\Components\Section::make()
                    ->compact()
                    ->schema([
                        Forms\Components\Grid::make(4)->schema([
                            Forms\Components\Select::make('client_id')
                                ->label('Cliente')
                                ->options(Client::all()->pluck('name', 'id'))
                                ->searchable()
                                ->required(),

                            Forms\Components\Select::make('billing_type')
                                ->label('Facturación')
                                ->options(['fiscal' => 'Fiscal', 'informal' => 'Interno', 'mixed' => 'Mixto'])
                                ->default('fiscal')
                                ->required(),
                            
                            // GRUPO DE ESTADO
                            Forms\Components\Group::make([
                                Forms\Components\Select::make('status')
                                    ->label('Estado')
                                    ->options(OrderStatus::class)
                                    ->default(OrderStatus::Draft)
                                    ->required()
                                    ->live()
                                    // REGLA DE BLOQUEO: No permitir volver atrás si ya está avanzado (salvo Admin)
                                    ->disableOptionWhen(function ($value, $state, ?Order $record) {
                                        if (!$record) return false; // Si es nuevo, todo permitido
                                        
                                        // Definir pesos de estados
                                        $weights = [
                                            'draft' => 1, 'processing' => 2, 'assembled' => 3, 
                                            'checked' => 4, 'standby' => 4, 'dispatched' => 5, 
                                            'delivered' => 6, 'paid' => 7, 'cancelled' => 0
                                        ];

                                        $currentStatus = $record->status instanceof OrderStatus ? $record->status->value : $record->status;
                                        $currentWeight = $weights[$currentStatus] ?? 0;
                                        
                                        // Si el pedido ya pasó de 'Checked' (peso 4), BLOQUEAMOS volver a Draft(1) o Processing(2)
                                        // Solo permitimos avanzar o Cancelar/Standby
                                        if ($currentWeight >= 4) {
                                            $targetWeight = $weights[$value] ?? 0;
                                            // Permitimos Cancelar (0) o Standby (4)
                                            if ($value === 'cancelled' || $value === 'standby') return false;
                                            // Bloqueamos ir hacia atrás
                                            return $targetWeight < $currentWeight;
                                        }
                                        return false;
                                    }),
                                
                                Forms\Components\Textarea::make('status_reason')
                                    ->label('Motivo del cambio')
                                    ->placeholder('Requerido al cambiar estado...')
                                    ->rows(2)
                                    ->required(fn (Get $get, ?Order $record) => $record && $get('status') !== ($record->status instanceof OrderStatus ? $record->status->value : $record->status))
                                    ->visible(fn (Get $get, ?Order $record) => $record && $get('status') !== ($record->status instanceof OrderStatus ? $record->status->value : $record->status))
                                    ->columnSpanFull(),
                            ])->columnSpan(1),

                            // COLUMNA FECHA Y PRIORIDAD
                            Forms\Components\Group::make([
                                Forms\Components\DatePicker::make('order_date')
                                    ->label('Fecha')
                                    ->default(now())
                                    ->required(),

                                // ¡AQUÍ ESTÁ TU CAMPO DE PRIORIDAD!
                                Forms\Components\Select::make('priority')
                                    ->label('Prioridad')
                                    ->options([
                                        1 => 'Normal',
                                        2 => 'Alta (Destacar)',
                                        3 => 'Urgente (Tope)'
                                    ])
                                    ->default(1)
                                    ->selectablePlaceholder(false)
                                    ->required(),
                            ])->columnSpan(1),
                        ])
                    ]),

                // --- CARGA DE ARTÍCULOS ---
                Forms\Components\Section::make('Carga de Artículos')
                    ->compact()
                    ->headerActions([
                        Forms\Components\Actions\Action::make('matrix_load')
                            ->label('GENERAR MÚLTIPLES')
                            ->color('warning')
                            ->icon('heroicon-o-squares-2x2')
                            ->modalWidth('7xl')
                            ->form([
                                Forms\Components\Select::make('article_id')->label('Artículo Base')->options(Article::all()->mapWithKeys(fn($a) => [$a->id => "{$a->code} - {$a->name}"]))
                                    ->searchable()->live()->required()
                                    ->afterStateUpdated(function ($state, Set $set) {
                                        if (!$state) return $set('matrix', []);
                                        $colors = Sku::where('article_id', $state)->join('colors', 'skus.color_id', '=', 'colors.id')->select('colors.id', 'colors.name', 'colors.hex_code')->distinct()->get();
                                        $set('matrix', $colors->map(fn($c) => ['color_id' => $c->id, 'color_name' => $c->name, 'color_hex' => $c->hex_code])->toArray());
                                    }),
                                Forms\Components\Repeater::make('matrix')->hiddenLabel()->hidden(fn (Get $get) => !$get('article_id'))->addable(false)->deletable(false)->reorderable(false)
                                    ->extraAttributes(['class' => 'matriz-erp-ultra-compacta'])
                                    ->schema(function (Get $get) {
                                        $articleId = $get('article_id');
                                        if (!$articleId) return [];
                                        $sizes = Sku::where('article_id', $articleId)->join('sizes', 'skus.size_id', '=', 'sizes.id')->select('sizes.id', 'sizes.name')->distinct()->orderBy('sizes.id')->get();
                                        $numTalles = count($sizes);
                                        return [
                                            Forms\Components\Placeholder::make('css_fix')->hiddenLabel()->content(new HtmlString("<style>.matriz-erp-ultra-compacta .fi-fo-repeater-item { padding: 0 !important; margin: 0 !important; border: none !important; background: transparent !important; } .matriz-erp-ultra-compacta .fi-fo-repeater-item-content { padding: 0 !important; } .matriz-erp-ultra-compacta .fi-fo-repeater-item-content > .grid { display: grid !important; grid-template-columns: 110px 30px repeat($numTalles, 1fr) !important; gap: 1px !important; align-items: center !important; } .matriz-erp-ultra-compacta input { height: 26px !important; font-size: 11px !important; border-radius: 2px !important; text-align: center !important; font-weight: bold !important; padding: 0 !important; } .btn-rayo-erp button { width: 22px !important; height: 22px !important; padding: 0 !important; } .matriz-erp-ultra-compacta label { display: none !important; }</style>"))->columnSpanFull(),
                                            Forms\Components\Placeholder::make('color_label')->hiddenLabel()->content(function ($state, Get $get) { $name = $get('color_name') ?? 'Color'; $hex = $get('color_hex') ?? '#ccc'; return new HtmlString("<div class='flex items-center gap-1.5 px-1 overflow-hidden'><div class='w-3 h-3 rounded-full flex-shrink-0' style='background-color:{$hex}; border: 1px solid rgba(0,0,0,0.1)'></div><span class='text-[10px] font-bold truncate uppercase text-gray-800'>{$name}</span></div>"); }),
                                            Forms\Components\Actions::make([ Forms\Components\Actions\Action::make('fill')->icon('heroicon-m-bolt')->color('info')->iconButton()->tooltip('Llenar fila')->extraAttributes(['class' => 'btn-rayo-erp'])->action(function (Set $set, Get $get) use ($sizes) { $row = $get(''); $val = collect($row)->first(fn($v, $k) => str_starts_with($k, 'size_') && $v > 0) ?? 0; foreach ($sizes as $s) { $set("size_{$s->id}", $val); } }) ]),
                                            ...collect($sizes)->map(fn($size) => Forms\Components\TextInput::make("size_{$size->id}")->placeholder("{$size->name}")->numeric()->default(0)->extraInputAttributes(['title' => "Talle {$size->name}", 'onclick' => 'this.select()']))->toArray(),
                                            Forms\Components\Hidden::make('color_id'),
                                        ];
                                    })->columns(1)
                            ])
                            ->action(function (array $data, Set $set, Get $get) {
                                // (Lógica de matriz igual que antes)
                                $articleId = $data['article_id']; $article = Article::find($articleId); $newVariants = [];
                                foreach ($data['matrix'] as $row) {
                                    $colorId = $row['color_id'];
                                    foreach ($row as $key => $quantity) {
                                        if (str_starts_with($key, 'size_') && intval($quantity) > 0) {
                                            $sizeId = str_replace('size_', '', $key); $sku = Sku::where('article_id', $articleId)->where('color_id', $colorId)->where('size_id', $sizeId)->first();
                                            if ($sku) { $newVariants[] = ['color_id' => $colorId, 'sku_id' => $sku->id, 'size_id' => $sizeId, 'quantity' => intval($quantity), 'unit_price' => $article->base_cost]; }
                                        }
                                    }
                                }
                                $currentGroups = $get('article_groups') ?? []; $groupIndex = collect($currentGroups)->search(fn($g) => ($g['article_id'] ?? null) == $articleId);
                                if ($groupIndex !== false) { $currentGroups[$groupIndex]['variants'] = array_merge($currentGroups[$groupIndex]['variants'] ?? [], $newVariants); } else { $currentGroups[] = ['article_id' => $articleId, 'variants' => $newVariants]; }
                                $set('article_groups', $currentGroups);
                            }),
                    ])
                    ->schema([
                        Forms\Components\Repeater::make('article_groups')
                            ->live()
                            ->hiddenLabel()->addActionLabel('Agregar otro Artículo')->defaultItems(0)->collapsible()
                            ->itemLabel(function (array $state): ?HtmlString {
                                $articleId = $state['article_id'] ?? null; if (!$articleId) return new HtmlString("<span class='text-gray-400 italic'>Nuevo Artículo...</span>"); $article = Article::find($articleId); if (!$article) return null;
                                $variants = $state['variants'] ?? []; $qty = collect($variants)->sum('quantity'); $total = collect($variants)->sum(fn($v) => (intval($v['quantity'] ?? 0) * floatval($v['unit_price'] ?? 0)));
                                return new HtmlString("<div class='flex justify-between items-center w-full px-1'><div class='flex items-center gap-2 overflow-hidden'><span class='font-black text-primary-600 text-lg'>{$article->code}</span><span class='font-medium text-gray-800 truncate'>{$article->name}</span></div><div class='flex items-center gap-2 text-sm whitespace-nowrap ml-2'><span class='bg-gray-100 text-gray-700 px-2 py-0.5 rounded font-bold'>{$qty} u.</span><span class='bg-green-50 text-green-700 px-2 py-0.5 rounded font-bold'>$ " . number_format($total, 0, ',', '.') . "</span></div></div>");
                            })
                            ->schema([
                                Forms\Components\Select::make('article_id')->hiddenLabel()->placeholder('Buscar Artículo...')->options(Article::all()->mapWithKeys(fn($a) => [$a->id => "{$a->code} - {$a->name}"]))
                                    ->searchable()->required()->live()->afterStateUpdated(fn (Set $set) => $set('variants', [['color_id' => null, 'sku_id' => null, 'size_id' => null, 'quantity' => null]]))->columnSpanFull(),

                                Forms\Components\Grid::make(12)
                                    ->extraAttributes(['class' => 'mb-1 border-b border-gray-200 pb-1'])
                                    ->schema(function (Get $get, ?Order $record) use ($checkIsAssembled) {
                                        $isAssembled = $checkIsAssembled($get, $record);
                                        return [
                                            Forms\Components\Placeholder::make('h1')->content('COLOR')->hiddenLabel()->extraAttributes(['class' => 'text-xs font-bold text-gray-500'])->columnSpan(3),
                                            Forms\Components\Placeholder::make('h2')->content('TALLE')->hiddenLabel()->extraAttributes(['class' => 'text-xs font-bold text-gray-500'])->columnSpan(2),
                                            Forms\Components\Placeholder::make('h3')->content($isAssembled ? 'PEDIDO' : 'CANT.')->hiddenLabel()->extraAttributes(['class' => 'text-xs font-bold text-gray-500 text-center'])->columnSpan($isAssembled ? 1 : 2),
                                            Forms\Components\Placeholder::make('h_pack')->content('ARMADO')->hiddenLabel()->extraAttributes(['class' => 'text-xs font-bold text-blue-600 text-center'])->columnSpan(1)->visible($isAssembled),
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
                                            Forms\Components\Placeholder::make('css_limpiador')->content(new HtmlString('<style>.tabla-ultra-compacta .fi-fo-repeater-item { padding: 0 !important; margin: 0 !important; border: none !important; background: transparent !important; box-shadow: none !important; } .tabla-ultra-compacta .fi-fo-repeater-item-content { padding: 0 !important; } .tabla-ultra-compacta .grid { gap: 4px !important; }</style>'))->columnSpanFull()->hiddenLabel(),
                                            Forms\Components\Hidden::make('size_id'),

                                            Forms\Components\Grid::make(12)
                                                ->extraAttributes(['class' => 'items-center !gap-x-1 !gap-y-0 !m-0 !p-0'])
                                                ->schema([
                                                    Forms\Components\Select::make('color_id')
                                                        ->hiddenLabel()->allowHtml()->searchable()
                                                        ->options(function (Get $get) { 
                                                            $articleId = $get('../../article_id'); if (!$articleId) return []; 
                                                            return Sku::where('article_id', $articleId)->join('colors', 'skus.color_id', '=', 'colors.id')->select('colors.id', 'colors.name', 'colors.hex_code')->distinct()->get()->mapWithKeys(fn ($c) => [$c->id => "<div class='flex items-center gap-2'><div class='w-3 h-3 rounded-full border border-gray-300' style='background-color:{$c->hex_code}'></div><span class='text-xs font-medium'>{$c->name}</span></div>"]); 
                                                        })
                                                        ->disableOptionWhen(function ($value, $state, Get $get) {
                                                            $variants = $get('../../variants') ?? [];
                                                            $selectedColors = collect($variants)->pluck('color_id')->toArray();
                                                            return in_array($value, $selectedColors) && $value !== $state;
                                                        })
                                                        ->live()
                                                        ->afterStateUpdated(fn(Get $get, Set $set) => $autoAddRow($get, $set))
                                                        ->columnSpan(3)->extraInputAttributes(['class' => '!h-7 !py-0 text-xs']),

                                                    Forms\Components\Select::make('sku_id')->hiddenLabel()->searchable()
                                                        ->options(function (Get $get) { $articleId = $get('../../article_id'); $colorId = $get('color_id'); if (!$articleId || !$colorId) return []; return Sku::where('article_id', $articleId)->where('color_id', $colorId)->with('size')->get()->mapWithKeys(fn($s) => [$s->id => $s->size->name . ' (Stock: '.$s->stock_quantity.')']); })
                                                        ->live()->afterStateUpdated(function ($state, Set $set, Get $get) use ($autoAddRow) { $sku = Sku::find($state); if($sku) { $set('unit_price', $sku->article->base_cost); $set('size_id', $sku->size_id); } $autoAddRow($get, $set); })->columnSpan($isAssembled ? 2 : 3)->extraInputAttributes(['class' => '!h-7 !py-0 text-xs']),

                                                    Forms\Components\TextInput::make('quantity')->hiddenLabel()->numeric()->live()->dehydrated()->afterStateUpdated(fn(Get $get, Set $set) => $autoAddRow($get, $set))->columnSpan($isAssembled ? 1 : 2)->extraInputAttributes(['class' => '!h-7 !py-0 text-center font-bold text-sm']),
                                                    Forms\Components\TextInput::make('packed_quantity')->hiddenLabel()->numeric()->default(0)->columnSpan(1)->visible($isAssembled)->disabled()->dehydrated(false)->extraInputAttributes(['class' => '!h-7 !py-0 text-center font-bold text-sm bg-blue-50 border-blue-500 text-blue-700', 'title' => 'Cantidad Realmente Armada']),
                                                    Forms\Components\TextInput::make('unit_price')->hiddenLabel()->numeric()->disabled()->dehydrated()->columnSpan(3)->extraInputAttributes(['class' => '!h-7 !py-0 bg-gray-50/50 text-xs']),
                                                    Forms\Components\Actions::make([ Forms\Components\Actions\Action::make('delete')->icon('heroicon-m-trash')->color('danger')->iconButton()->size(ActionSize::Small)->action(function ($component, Forms\Set $set, Forms\Get $get) { $uuid = (string) str($component->getContainer()->getStatePath())->afterLast('.'); $items = $get('../'); if (isset($items[$uuid])) { unset($items[$uuid]); $set('../', $items); Notification::make()->title('Eliminado')->success()->send(); } }), ])->columnSpan(1)->extraAttributes(['class' => 'flex justify-center items-center h-7']),
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
            ->modifyQueryUsing(fn ($query) => $query->orderByRaw("COALESCE(parent_id, id) DESC, id ASC"))
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('#')
                    ->formatStateUsing(function (Order $record) {
                        if ($record->parent_id) return new HtmlString("<span style='margin-left: 15px; color: #9ca3af;'>└─</span> " . $record->id);
                        return $record->id;
                    })
                    ->color(fn (Order $r) => $r->parent_id ? 'gray' : null)
                    ->searchable(),

                Tables\Columns\TextColumn::make('client.name')
                    ->label('Cliente / Zona')
                    ->weight('bold')
                    ->searchable()
                    ->description(fn (Order $record) => new HtmlString(
                        ($record->client->locality->name ?? '-') . 
                        ($record->client->locality?->zone ? " <span class='text-xs bg-gray-100 text-gray-600 px-1 rounded border'>{$record->client->locality->zone->name}</span>" : '')
                    )),

                Tables\Columns\TextColumn::make('order_date')->date('d/m/Y')->label('Fecha'),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->label('Estado')
                    ->color(fn ($state) => match ($state) {
                        OrderStatus::Draft => 'gray',
                        OrderStatus::Processing => 'warning',
                        OrderStatus::Assembled => 'info',
                        OrderStatus::Checked => 'primary',
                        OrderStatus::Dispatched => 'gray',
                        OrderStatus::Delivered => 'danger',
                        OrderStatus::Paid => 'success',
                        OrderStatus::Cancelled => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('total_amount')->money('ARS')->label('Total')->weight('black'),
            ])
            ->defaultSort(null)
            ->actions([
                Tables\Actions\EditAction::make(),

                Tables\Actions\Action::make('change_status')
                    ->label('Estado')
                    ->icon('heroicon-m-arrow-path')
                    ->color('warning')
                    ->button()
                    ->modalWidth('md')
                    ->modalHeading(fn($record) => "Cambiar estado del Pedido #{$record->id}")
                    ->form([
                        Forms\Components\Select::make('new_status')
                            ->label('Nuevo Estado')
                            ->options(OrderStatus::class)
                            ->default(fn($record) => $record->status)
                            ->required()
                            ->reactive(),

                        Forms\Components\Textarea::make('reason')
                            ->label('Motivo del cambio')
                            ->placeholder('Explique por qué cambia el estado...')
                            ->visible(function (Get $get, Order $record) {
                                $newStatus = $get('new_status');
                                $currentVal = $record->status instanceof OrderStatus ? $record->status->value : $record->status;
                                $newStatusVal = $newStatus instanceof OrderStatus ? $newStatus->value : $newStatus;

                                $orderOfStatus = [
                                    'draft' => 1, 'processing' => 2, 'assembled' => 3, 
                                    'checked' => 4, 'dispatched' => 5, 'delivered' => 6, 'paid' => 7
                                ];
                                
                                $currWeight = $orderOfStatus[$currentVal] ?? 0;
                                $newWeight = $orderOfStatus[$newStatusVal] ?? 0;

                                return $newStatusVal === 'cancelled' || $newWeight < $currWeight;
                            })
                            ->required(fn (Get $get) => $get('reason') !== null)
                    ])
                    ->action(function (Order $record, array $data) {
                        if (!empty($data['reason'])) {
                            // activity()->on($record)->log('Cambio estado: ' . $data['reason']);
                        }
                        $record->update(['status' => $data['new_status']]);
                        Notification::make()->title('Estado actualizado')->success()->send();
                    }),
            ])
            // --- AQUÍ ESTÁ EL LANZADOR LOGÍSTICO (BULK ACTION) ---
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),

                    Tables\Actions\BulkAction::make('send_to_packing')
                        ->label('Enviar a Armado (Lanzador)')
                        ->icon('heroicon-o-rocket-launch')
                        ->color('success')
                        ->modalWidth('xl')
                        ->modalHeading('Lanzamiento de Pedidos a Depósito')
                        ->modalDescription('Filtre por zona y asigne prioridad. Solo se enviarán los pedidos que coincidan con las localidades seleccionadas.')
                        ->form([
                            Forms\Components\Grid::make(2)->schema([
                                // 1. SELECCIÓN DE ZONA
                                Forms\Components\Select::make('zone_ids')
                                    ->label('1. Filtrar por Zona')
                                    ->options(Zone::all()->pluck('name', 'id'))
                                    ->multiple()
                                    ->preload()
                                    ->live() 
                                    ->afterStateUpdated(fn (Set $set) => $set('locality_ids', [])) 
                                    ->columnSpanFull(),

                                // 2. SELECCIÓN DE LOCALIDADES (Reactivo)
                                Forms\Components\CheckboxList::make('locality_ids')
                                    ->label('2. Confirmar Localidades')
                                    ->options(function (Get $get) {
                                        $zones = $get('zone_ids');
                                        if (empty($zones)) return []; 
                                        
                                        return Locality::whereIn('zone_id', $zones)
                                            ->orderBy('name')
                                            ->pluck('name', 'id');
                                    })
                                    ->bulkToggleable()
                                    ->columns(3)
                                    ->gridDirection('row')
                                    ->columnSpanFull()
                                    ->hidden(fn (Get $get) => empty($get('zone_ids')))
                                    ->required(),

                                // 3. PRIORIDAD
                                Forms\Components\Radio::make('priority')
                                    ->label('3. Asignar Prioridad')
                                    ->options([
                                        1 => 'Normal',
                                        2 => 'Alta (Destacar)',
                                        3 => 'Urgente (Tope de lista)'
                                    ])
                                    ->descriptions([
                                        1 => 'Orden cronológico estándar.',
                                        2 => 'Aparece con borde ROJO en el armador.',
                                        3 => 'Se mueve al principio de la lista de armado.'
                                    ])
                                    ->default(1)
                                    ->required()
                                    ->columnSpanFull(),
                            ]),
                        ])
                        ->action(function (Collection $records, array $data) {
                            $allowedLocalityIds = $data['locality_ids'] ?? [];
                            $priority = $data['priority'];
                            $count = 0;

                            foreach ($records as $order) {
                                // VALIDACIÓN: Que pertenezca a la localidad filtrada
                                if (in_array($order->client->locality_id, $allowedLocalityIds)) {
                                    // Y que no esté ya cerrado
                                    if (!in_array($order->status, [
                                        OrderStatus::Dispatched, 
                                        OrderStatus::Delivered, 
                                        OrderStatus::Cancelled
                                    ])) {
                                        $order->update([
                                            'status' => OrderStatus::Processing, 
                                            'priority' => $priority,
                                        ]);
                                        $count++;
                                    }
                                }
                            }

                            if ($count > 0) {
                                Notification::make()
                                    ->title("Lanzamiento Exitoso")
                                    ->body("Se enviaron {$count} pedidos a preparación con prioridad asignada.")
                                    ->success()
                                    ->send();
                            } else {
                                Notification::make()
                                    ->title("Sin cambios")
                                    ->body("Ninguno de los pedidos seleccionados coincidía con las localidades elegidas.")
                                    ->warning()
                                    ->send();
                            }
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ]);
    }

    public static function getRelations(): array { return []; }
    public static function getPages(): array { return [ 'index' => Pages\ListOrders::route('/'), 'create' => Pages\CreateOrder::route('/create'), 'edit' => Pages\EditOrder::route('/{record}/edit'), ]; }
}