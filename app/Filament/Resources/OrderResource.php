<?php
namespace App\Filament\Resources;

use App\Enums\OrderStatus;
use App\Filament\Resources\OrderResource\Pages;
use App\Models\Article;
use App\Models\Order;
use App\Models\Sku;
use App\Models\Client;
use App\Models\Zone;
use App\Models\Locality;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Facades\DB;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Collection;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;
    protected static ?string $navigationIcon = 'heroicon-o-shopping-cart';
    protected static ?string $navigationGroup = 'Ventas';
    protected static ?string $modelLabel = 'Pedido';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make()
                    ->compact()
                    ->schema([
                        Forms\Components\Grid::make(5)->schema([
                            Forms\Components\Select::make('client_id')
                                ->relationship('client', 'name')
                                ->searchable()
                                ->required()
                                ->disabled(function (Get $get) {
                                    $status = $get('status');
                                    $val = $status instanceof \BackedEnum ? $status->value : $status;
                                    return $val !== 'draft';
                                }),

                            Forms\Components\Select::make('billing_type')
                                ->label('Facturación')
                                ->options(['fiscal' => 'Fiscal', 'informal' => 'Interno', 'mixed' => 'Mixto'])
                                ->default('fiscal')
                                ->required()
                                ->disabled(function (Get $get) {
                                    $status = $get('status');
                                    $val = $status instanceof \BackedEnum ? $status->value : $status;
                                    return !in_array($val, ['draft', 'processing', 'standby']);
                                }),
                           
                            Forms\Components\Select::make('status')
                                ->options(OrderStatus::class)
                                ->default(OrderStatus::Draft)
                                ->live()
                                ->required()
                                ->disableOptionWhen(function ($value, ?Order $record, Get $get) {
                                    if (!$record) return false;
                                    
                                    $dbStatus = $record->getOriginal('status');
                                    if ($dbStatus instanceof \BackedEnum) $dbStatus = $dbStatus->value;

                                    if ($get('billing_type') === 'mixed') {
                                        $totalArmado = $record->items->sum('packed_quantity');
                                        if ($totalArmado % 2 !== 0 && in_array($value, ['checked', 'dispatched', 'paid'])) {
                                            return true; 
                                        }
                                    }

                                    if ($value === 'standby') {
                                        $estadosHabilitantes = ['assembled', 'checked', 'dispatched'];
                                        return !in_array($dbStatus, $estadosHabilitantes);
                                    }

                                    if (in_array($value, ['checked', 'dispatched', 'paid'])) {
                                        if (!$record->invoices()->where('invoice_type', 'B')->exists() && $value !== $dbStatus) {
                                            return true;
                                        }
                                    }

                                    if ($dbStatus === 'standby') {
                                        $permitidos = ['dispatched', 'paid', 'cancelled', 'standby'];
                                        if (!in_array($value, $permitidos)) return true;

                                        $hijosPendientes = $record->children()
                                            ->whereNotIn('status', ['assembled', 'checked', 'dispatched', 'paid', 'cancelled'])
                                            ->exists();
                                        if ($hijosPendientes && in_array($value, ['dispatched', 'paid'])) return true;
                                    }

                                    $estadosCerrados = ['checked', 'dispatched', 'paid'];
                                    if (in_array($dbStatus, $estadosCerrados)) {
                                        if (in_array($value, ['draft', 'processing', 'assembled'])) return true;
                                        if ($dbStatus !== 'checked' && $value === 'checked') return true;
                                    }

                                    if ($dbStatus === 'processing') return !in_array($value, ['draft', 'cancelled', 'processing']);
                                    if ($record->parent_id) return !in_array($value, ['cancelled', $record->status->value]);

                                    return false;
                                })
                                ->helperText(function (Get $get, ?Order $record) {
                                    if ($get('billing_type') === 'mixed') {
                                        $total = $record?->items->sum('packed_quantity') ?? 0;
                                        if ($total % 2 !== 0) return "Atención: Facturación Mixta requiere cantidad PAR (Actual: $total).";
                                    }
                                    return null;
                                }),
                                
                            Forms\Components\DatePicker::make('order_date')
                                ->label('Fecha')
                                ->default(now())
                                ->required()
                                ->disabled(fn (Get $get) => ($get('status') instanceof \BackedEnum ? $get('status')->value : $get('status')) !== 'draft'),

                            Forms\Components\Select::make('priority')
                                ->label('Prioridad')
                                ->options([1 => 'Normal', 2 => 'Alta', 3 => 'Urgente'])
                                ->default(1)->required(),
                        ])
                    ]),

                Forms\Components\Section::make('Matriz de Mercadería')
                    ->headerActions([
                        Forms\Components\Actions\Action::make('add_article')
                            ->label('Añadir Artículo')
                            ->color('success')
                            ->icon('heroicon-m-plus')
                            ->visible(function (Get $get) {
                                $status = $get('status');
                                $val = $status instanceof \BackedEnum ? $status->value : $status;
                                return in_array($val, ['draft', 'standby']);
                            })
                            ->form([
                                Forms\Components\Select::make('article_id')
                                    ->label('Artículo')
                                    ->options(function (Get $get) {
                                        $existing = collect($get('article_groups'))->pluck('article_id')
                                            ->merge(collect($get('child_groups'))->pluck('article_id'))->toArray();
                                        return Article::whereNotIn('id', $existing)->get()
                                            ->mapWithKeys(fn($a) => [$a->id => "{$a->code} - {$a->name}"]);
                                    })->searchable()->required()
                            ])
                            ->action(function (array $data, Set $set, Get $get) {
                                $target = ($get('status') instanceof \BackedEnum && $get('status')->value === 'standby') || $get('status') === 'standby' ? 'child_groups' : 'article_groups';
                                $groups = $get($target) ?? [];
                                $article = Article::find($data['article_id']);
                                $colors = Sku::where('article_id', $article->id)->join('colors', 'colors.id', '=', 'skus.color_id')->select('colors.id', 'colors.name', 'colors.hex_code')->distinct()->get();
                                $matrix = [];
                                foreach ($colors as $color) {
                                    $matrix[uniqid()] = ['color_id' => $color->id, 'color_name' => $color->name, 'color_hex' => $color->hex_code];
                                }
                                $groups[uniqid()] = ['article_id' => $article->id, 'matrix' => $matrix];
                                $set($target, $groups);
                            })
                    ])
                    ->schema([
                        Forms\Components\ViewField::make('article_groups')
                            ->view('filament.components.order-matrix-editor')
                            ->columnSpanFull()
                            ->reactive()
                            ->registerActions([
                                Forms\Components\Actions\Action::make('removeArticle')
                                    ->action(function (array $arguments, Set $set, Get $get) {
                                        $current = $get('article_groups'); unset($current[$arguments['groupKey']]); $set('article_groups', $current);
                                    }),
                                Forms\Components\Actions\Action::make('removeChildGroup')
                                    ->action(function (array $arguments, Set $set, Get $get) {
                                        $groups = $get('child_groups'); unset($groups[$arguments['key']]); $set('child_groups', $groups);
                                    }),
                                Forms\Components\Actions\Action::make('fillRow')
                                    ->action(function (array $arguments, Set $set, Get $get) {
                                        $uuid = $arguments['uuid']; $gk = $arguments['groupKey'];
                                        $row = $get("article_groups.{$gk}.matrix.{$uuid}");
                                        $val = 0;
                                        foreach ($row as $k => $v) { if (str_starts_with($k, 'qty_') && (int)$v > 0) { $val = (int)$v; break; } }
                                        $sizes = Sku::where('article_id', $get("article_groups.{$gk}.article_id"))->pluck('size_id')->unique();
                                        foreach ($sizes as $sId) { $set("article_groups.{$gk}.matrix.{$uuid}.qty_{$sId}", $val); }
                                    }),
                                Forms\Components\Actions\Action::make('fillChildRow')
                                    ->action(function (array $arguments, Set $set, Get $get) {
                                        $uuid = $arguments['uuid']; $k = $arguments['key'];
                                        $row = $get("child_groups.{$k}.matrix.{$uuid}");
                                        $val = 0;
                                        foreach ($row as $key => $v) { if (str_starts_with($key, 'qty_') && (int)$v != 0) { $val = (int)$v; break; } }
                                        $sizes = Sku::where('article_id', $get("child_groups.{$k}.article_id"))->pluck('size_id')->unique();
                                        foreach ($sizes as $sId) { $set("child_groups.{$k}.matrix.{$uuid}.qty_{$sId}", $val); }
                                    }),
                            ]),
                    ]),
            ]);
    }
    
    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->orderByRaw("COALESCE(parent_id, id) DESC, id ASC"))
            ->columns([
                Tables\Columns\TextColumn::make('id')->label('#')->sortable(),
                Tables\Columns\TextColumn::make('client.name')
                    ->label('Cliente / Zona')
                    ->weight('bold')
                    ->searchable()
                    ->formatStateUsing(function ($state, Order $record) {
                        if ($record->parent_id) {
                            return new HtmlString('<div class="flex items-center gap-2 pl-8 text-slate-500 italic">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M9 5l7 7-7 7"></path></svg> 
                                ' . $state . ' <span class="text-[9px] bg-slate-800 px-1.5 py-0.5 rounded-full border border-slate-700 not-italic font-black text-slate-400">HIJO</span></div>');
                        }
                        return $state;
                    })
                    ->description(fn (Order $record) => ($record->client->locality->name ?? '-') . ($record->client->locality?->zone ? " ({$record->client->locality->zone->name})" : '')),
                
                Tables\Columns\TextColumn::make('order_date')->date('d/m/Y')->label('Fecha')->sortable(),
                Tables\Columns\TextColumn::make('status')->badge()->label('Estado'),
                Tables\Columns\TextColumn::make('total_amount')->money('ARS')->label('Total')->weight('black'),
            ])
            ->filtersLayout(Tables\Enums\FiltersLayout::AboveContent)
            ->headerActions([
                // 1. EL BOTÓN DEL CAI (NUEVO)
                Tables\Actions\Action::make('configurar_cai')
                    ->label('Talonario (CAI)')
                    ->icon('heroicon-o-cog-8-tooth')
                    // MAGIA: El botón se pone ROJO si el CAI vence en menos de 30 días
                    ->color(function () {
                        $settings = \App\Models\Setting::first();
                        if (!$settings || !$settings->cai_expiry) return 'danger';
                        $diasRestantes = \Carbon\Carbon::now()->diffInDays(\Carbon\Carbon::parse($settings->cai_expiry), false);
                        return $diasRestantes <= 30 ? 'danger' : 'gray';
                    })
                    ->fillForm(function () {
                        $settings = \App\Models\Setting::firstOrCreate(['id' => 1]);
                        return $settings->toArray();
                    })
                    ->form([
                        Forms\Components\TextInput::make('cai_number')
                            ->label('Número de C.A.I. (AFIP)')
                            ->required(),
                        Forms\Components\DatePicker::make('cai_expiry')
                            ->label('Fecha de Vencimiento')
                            ->required()
                            ->helperText(function () {
                                $settings = \App\Models\Setting::first();
                                if (!$settings || !$settings->cai_expiry) return '';
                                $dias = \Carbon\Carbon::now()->diffInDays(\Carbon\Carbon::parse($settings->cai_expiry), false);
                                if ($dias < 0) return new HtmlString("<span class='text-red-600 font-bold'>¡El CAI está vencido hace " . abs($dias) . " días!</span>");
                                if ($dias <= 30) return new HtmlString("<span class='text-red-600 font-bold'>¡Atención! Vence en {$dias} días.</span>");
                                return "Válido por {$dias} días más.";
                            }),
                        Forms\Components\Grid::make(2)->schema([
                            Forms\Components\TextInput::make('remito_pv')
                                ->label('Punto de Venta')
                                ->numeric()
                                ->default(1)
                                ->required(),
                            Forms\Components\TextInput::make('next_remito_number')
                                ->label('Siguiente Nro de Remito')
                                ->numeric()
                                ->required(),
                        ]),
                    ])
                    ->action(function (array $data) {
                        $settings = \App\Models\Setting::firstOrCreate(['id' => 1]);
                        $settings->update($data);
                        Notification::make()->success()->title('Talonario Actualizado')->send();
                    })
                    ->modalWidth('md'),
                    
                Tables\Actions\Action::make('global_send_to_packing')
                    ->label('Lanzador Logístico')
                    ->icon('heroicon-o-rocket-launch')
                    ->color('success')
                    ->form([
                        Forms\Components\Select::make('zone_ids')->label('Zona')->options(Zone::all()->pluck('name', 'id'))->multiple()->live(),
                        Forms\Components\CheckboxList::make('locality_ids')->label('Localidades')
                            ->options(fn (Get $get) => Locality::whereIn('zone_id', $get('zone_ids') ?? [])->pluck('name', 'id'))->columns(3)->required()->bulkToggleable(),
                    ])
                    ->action(function (array $data) {
                        $parentOrders = Order::where('status', OrderStatus::Draft)
                            ->whereNull('parent_id')
                            ->whereHas('client', fn($q) => $q->whereIn('locality_id', $data['locality_ids']))
                            ->get();

                        $count = 0;
                        foreach ($parentOrders as $order) {
                            $order->update(['status' => OrderStatus::Processing]);
                            $order->children()->update(['status' => OrderStatus::Processing]); 
                            $count++;
                        }

                        Notification::make()->title("Lanzamiento: {$count} pedidos principales (y sus derivados) enviados a armado.")->success()->send();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->before(function (Collection $records, Tables\Actions\DeleteBulkAction $action) {
                            $hasInvalidOrders = $records->contains(function (Order $order) {
                                $status = $order->status instanceof \BackedEnum ? $order->status->value : $order->status;
                                return $status !== 'draft';
                            });

                            if ($hasInvalidOrders) {
                                Notification::make()
                                    ->danger()
                                    ->title('Acción Bloqueada')
                                    ->body('Solo puedes eliminar pedidos en estado "Borrador". Los demás deben ser cancelados.')
                                    ->persistent()
                                    ->send();
                                $action->halt(); 
                            }

                            foreach ($records as $record) {
                                $record->children()->delete();
                            }
                        }),
                ]),
            ])
            ->actions([
                // EDITAR (Solo Padres y en estados permitidos)
                Tables\Actions\EditAction::make()
                    ->visible(function (Order $record) {
                        $status = $record->status instanceof \BackedEnum ? $record->status->value : $record->status;
                        $isEditable = !in_array($status, ['dispatched', 'paid', 'cancelled']);
                        return $isEditable && is_null($record->parent_id);
                    }),

                // ==========================================
                // BOTONES DIRECTOS (FLUJO PRINCIPAL)
                // ==========================================

                // DRAFT -> PROCESSING (A Armar)
                Tables\Actions\Action::make('enviar_a_armar')
                    ->label('A Armar')
                    ->icon('heroicon-m-inbox-arrow-down')
                    ->color('warning')
                    ->button()
                    ->visible(fn (Order $record) => ($record->status instanceof \BackedEnum ? $record->status->value : $record->status) === 'draft' && is_null($record->parent_id))
                    ->requiresConfirmation()
                    ->action(function (Order $record) {
                        $record->update(['status' => OrderStatus::Processing]);
                        $record->children()->update(['status' => OrderStatus::Processing]);
                    }),

                // ASSEMBLED / STANDBY -> CHECKED (FACTURAR)
                Tables\Actions\Action::make('facturar')
                    ->label('Facturar')
                    ->icon('heroicon-o-document-check')
                    ->color('success')
                    ->button()
                    ->modalWidth('7xl')
                    ->visible(fn (Order $record) => 
                        in_array($record->status instanceof \BackedEnum ? $record->status->value : $record->status, ['assembled', 'standby']) && 
                        !$record->invoices()->where('invoice_type', 'B')->exists() &&
                        is_null($record->parent_id)
                    )
                    ->form(function (Order $record) {
                        // FIX DEFINITIVO: Traemos el Padre y TODOS los hijos
                        $orderIds = \App\Models\Order::where('id', $record->id)
                            ->orWhere('parent_id', $record->id)
                            ->pluck('id')
                            ->toArray();
                            
                        $itemsAgrupados = \App\Models\OrderItem::with('article')
                            ->whereIn('order_id', $orderIds)
                            ->get();
                            
                        $grouped = $itemsAgrupados->groupBy('article_id');
                        $tbody = '';
                        $totalCostoPedido = 0;

                        foreach ($grouped as $articleId => $items) {
                            $qty = $items->sum(function($i) {
                                return $i->packed_quantity > 0 ? $i->packed_quantity : $i->quantity;
                            });
                            
                            if ($qty <= 0) continue;
                            
                            $price = $items->max('unit_price');
                            $subtotal = $qty * $price;
                            $totalCostoPedido += $subtotal;
                            
                            $article = $items->first()->article;
                            $codigo = $article ? $article->code : 'S/C';
                            $nombre = $article ? $article->name : 'Artículo Eliminado';
                            
                            $tbody .= "
                                <tr class='border-b border-gray-200 dark:border-white/10'>
                                    <td class='px-4 py-3 text-sm text-gray-950 dark:text-white'>{$codigo} - {$nombre}</td>
                                    <td class='px-4 py-3 text-sm text-center font-medium text-gray-950 dark:text-white'>{$qty}</td>
                                    <td class='px-4 py-3 text-sm text-right text-gray-950 dark:text-white'>$ " . number_format($price, 2, ',', '.') . "</td>
                                    <td class='px-4 py-3 text-sm font-bold text-right text-gray-950 dark:text-white'>$ " . number_format($subtotal, 2, ',', '.') . "</td>
                                </tr>
                            ";
                        }

                        $resumenHtml = "
                        <div class='fi-ta-content overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 mb-2'>
                            <div class='overflow-x-auto'>
                                <table class='w-full text-left divide-y divide-gray-200 dark:divide-white/5'>
                                    <thead class='bg-gray-50 dark:bg-white/5'>
                                        <tr>
                                            <th class='px-4 py-3 text-xs font-semibold text-gray-950 dark:text-white uppercase tracking-wider'>Artículo</th>
                                            <th class='px-4 py-3 text-xs font-semibold text-center text-gray-950 dark:text-white uppercase tracking-wider'>Cant.</th>
                                            <th class='px-4 py-3 text-xs font-semibold text-right text-gray-950 dark:text-white uppercase tracking-wider'>Precio U.</th>
                                            <th class='px-4 py-3 text-xs font-semibold text-right text-gray-950 dark:text-white uppercase tracking-wider'>Subtotal</th>
                                        </tr>
                                    </thead>
                                    <tbody class='divide-y divide-gray-200 dark:divide-white/5'>
                                        {$tbody}
                                    </tbody>
                                    <tfoot class='bg-gray-50 dark:bg-white/5'>
                                        <tr>
                                            <td colspan='3' class='px-4 py-4 text-right text-sm font-bold text-gray-950 dark:text-white uppercase tracking-wider'>
                                                Total Consolidado (Padre e Hijos):
                                            </td>
                                            <td class='px-4 py-4 text-right text-xl font-black text-primary-600 dark:text-primary-400'>
                                                $ " . number_format($totalCostoPedido, 2, ',', '.') . "
                                            </td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                        ";
                        
                        return [
                            Forms\Components\Placeholder::make('resumen_carga')
                                ->label('')
                                ->content(new HtmlString($resumenHtml)),

                            Forms\Components\Section::make('Configuración de Cobro')->schema([
                                Forms\Components\Grid::make(2)->schema([
                                    Forms\Components\Select::make('billing_type')
                                        ->label('Tipo de Facturación')
                                        ->options(['fiscal' => 'Fiscal (100% Blanco)', 'informal' => 'Interno (100% Negro)', 'mixed' => 'Mixto (50/50)'])
                                        ->default($record->client->billing_type ?? 'mixed')
                                        ->required(),
                                        
                                    Forms\Components\Select::make('payment_method')
                                        ->label('Método de Pago')
                                        ->options(['cta_cte' => 'Cta Cte', 'efectivo' => 'Efectivo', 'transferencia' => 'Transferencia', 'cheque' => 'Cheque'])
                                        ->default($record->client->last_payment_method ?? 'cta_cte')
                                        ->required()->live(),
                                ]),
                                
                                Forms\Components\Grid::make(2)->schema([
                                    Forms\Components\TextInput::make('bank_name')->label('Banco Origen')->placeholder('Ej: Galicia / MP')
                                        ->visible(fn (Get $get) => in_array($get('payment_method'), ['transferencia', 'cheque']))
                                        ->required(fn (Get $get) => in_array($get('payment_method'), ['transferencia', 'cheque'])),
                                    Forms\Components\TextInput::make('transaction_id')->label('Nro. Referencia')
                                        ->visible(fn (Get $get) => $get('payment_method') === 'transferencia')
                                        ->required(fn (Get $get) => $get('payment_method') === 'transferencia'),
                                    Forms\Components\TextInput::make('check_number')->label('Nro. Cheque')
                                        ->visible(fn (Get $get) => $get('payment_method') === 'cheque')
                                        ->required(fn (Get $get) => $get('payment_method') === 'cheque'),
                                    Forms\Components\DatePicker::make('due_date')->label('Vencimiento Cheque')
                                        ->visible(fn (Get $get) => $get('payment_method') === 'cheque')
                                        ->required(fn (Get $get) => $get('payment_method') === 'cheque'),
                                ]),
                            ]),
                        ];
                    })
                    ->action(function (Order $record, array $data) {
                        // Guardamos el tipo de facturación final elegido en el Padre para usarlo luego en los PDFs
                        $record->update(['billing_type' => $data['billing_type']]);
                        
                        // Si es informal, no llamamos a AFIP, solo cerramos el pedido
                        if ($data['billing_type'] === 'informal') {
                            $record->update([
                                'status' => OrderStatus::Checked,
                                'invoiced_at' => now(),
                            ]);
                            $record->children()->update(['status' => OrderStatus::Checked]);
                            Notification::make()->success()->title("Pedido verificado internamente (100% Negro)")->send();
                            return;
                        }

                        // Si es Fiscal o Mixto, llamamos a AFIP
                        $response = \App\Services\AfipService::facturar($record, $data);
                        if ($response['success']) {
                            $record->update([
                                'status' => OrderStatus::Checked,
                                'invoice_number' => $data['invoice_number'] ?? null,
                                'invoiced_at' => now(),
                            ]);
                            $record->children()->update(['status' => OrderStatus::Checked]);
                            Notification::make()->success()->title($response['message'])->send();
                        } else {
                            Notification::make()->danger()->title('Error AFIP')->body($response['error'])->persistent()->send();
                        }
                    })
                    ->requiresConfirmation(),

                // CHECKED -> DISPATCHED (Despachar)
                Tables\Actions\Action::make('despachar')
                    ->label('Cargar en Viajante')
                    ->icon('heroicon-m-truck')
                    ->color('primary')
                    ->button()
                    ->visible(fn (Order $record) => ($record->status instanceof \BackedEnum ? $record->status->value : $record->status) === 'checked' && is_null($record->parent_id))
                    ->requiresConfirmation()
                    ->action(function (Order $record) {
                        $record->update(['status' => OrderStatus::Dispatched]);
                        $record->children()->update(['status' => OrderStatus::Dispatched]);
                    }),

                // DISPATCHED -> PAID (Cobrar)
                Tables\Actions\Action::make('marcar_pagado')
                    ->label('Marcar Pagado')
                    ->icon('heroicon-m-currency-dollar')
                    ->color('success')
                    ->button()
                    ->visible(fn (Order $record) => ($record->status instanceof \BackedEnum ? $record->status->value : $record->status) === 'dispatched' && is_null($record->parent_id))
                    ->requiresConfirmation()
                    ->action(function (Order $record) {
                        $record->update(['status' => OrderStatus::Paid]);
                        $record->children()->update(['status' => OrderStatus::Paid]);
                    }),

                // ==========================================
                // EL NUEVO HUB DE IMPRESIÓN (FASE 6)
                // ==========================================
                Tables\Actions\ActionGroup::make([
                    
                    // 1. REMITOS (NO SALE EN INFORMAL)
                    Tables\Actions\Action::make('print_remitos')
                        ->label('Remitos (x3)')
                        ->icon('heroicon-o-document-duplicate')
                        ->color('gray')
                        ->visible(fn (Order $record) => 
                            $record->billing_type !== 'informal' && // REGLA: El 100% informal NO lleva remito
                            in_array($record->status instanceof \BackedEnum ? $record->status->value : $record->status, ['checked', 'dispatched', 'paid']) && 
                            is_null($record->parent_id)
                        )
                        ->url(fn (Order $record) => url('/admin/orders/'.$record->id.'/remito'))
                        ->openUrlInNewTab(),

                    // 2. FACTURA AFIP (Inteligente para múltiples facturas)
                    Tables\Actions\Action::make('descargar_factura')
                        ->label('Facturas AFIP')
                        ->icon('heroicon-o-document-text')
                        ->color('success')
                        ->visible(fn (Order $record) => $record->invoices()->where('invoice_type', 'B')->exists() && is_null($record->parent_id))
                        ->action(function (Order $record, array $data) {
                            // Si solo hay una, redirigimos directo
                            $invoices = $record->invoices()->where('invoice_type', 'B')->get();
                            if ($invoices->count() === 1) {
                                return redirect()->route('order.invoice.download', ['order' => $record->id, 'type' => 'B', 'invoice_id' => $invoices->first()->id]);
                            }
                        })
                        // Si hay más de una, Filament muestra este form en un modal automáticamente
                        ->form(function (Order $record) {
                            $invoices = $record->invoices()->where('invoice_type', 'B')->get();
                            if ($invoices->count() <= 1) return [];

                            return [
                                Forms\Components\Select::make('invoice_id')
                                    ->label('Seleccione la factura que desea ver')
                                    ->options($invoices->mapWithKeys(fn($i) => [$i->id => "Factura {$i->number} ({$i->created_at->format('d/m/Y H:i')})"]))
                                    ->required(),
                            ];
                        })
                        ->action(function (Order $record, array $data) {
                            $invoiceId = $data['invoice_id'] ?? $record->invoices()->where('invoice_type', 'B')->first()->id;
                            return redirect()->route('order.invoice.download', ['order' => $record->id, 'type' => 'B', 'invoice_id' => $invoiceId]);
                        }),

                    // 4. NOTA DE CRÉDITO (Inteligente para múltiples NC)
                    Tables\Actions\Action::make('descargar_nc')
                        ->label('Notas de Crédito')
                        ->icon('heroicon-o-document-minus')
                        ->color('danger')
                        ->visible(fn (Order $record) => $record->invoices()->where('invoice_type', 'NC')->exists() && is_null($record->parent_id))
                        ->form(function (Order $record) {
                            $ncs = $record->invoices()->where('invoice_type', 'NC')->get();
                            if ($ncs->count() <= 1) return [];

                            return [
                                Forms\Components\Select::make('invoice_id')
                                    ->label('Seleccione la Nota de Crédito')
                                    ->options($ncs->mapWithKeys(fn($nc) => [$nc->id => "NC {$nc->number} ({$nc->created_at->format('d/m/Y H:i')})"]))
                                    ->required(),
                            ];
                        })
                        ->action(function (Order $record, array $data) {
                            $invoices = $record->invoices()->where('invoice_type', 'NC')->get();
                            $invoiceId = $data['invoice_id'] ?? $invoices->first()->id;
                            return redirect()->route('order.invoice.download', ['order' => $record->id, 'type' => 'NC', 'invoice_id' => $invoiceId]);
                        }),

                ])
                ->label('🖨️ Documentos')
                ->icon('heroicon-m-printer')
                ->button()
                ->color('gray')
                ->visible(fn(Order $record) => is_null($record->parent_id) && in_array($record->status instanceof \BackedEnum ? $record->status->value : $record->status, ['checked', 'dispatched', 'paid'])),
                
                // ==========================================
                // ACCIONES ADMINISTRATIVAS SECUNDARIAS
                // ==========================================
                Tables\Actions\ActionGroup::make([
                    
                    // PASAR A STANDBY
                    Tables\Actions\Action::make('poner_en_standby')
                        ->label('Pausar (Standby)')
                        ->icon('heroicon-m-pause-circle')
                        ->color('warning')
                        ->visible(fn (Order $record) => 
                            in_array($record->status instanceof \BackedEnum ? $record->status->value : $record->status, ['assembled', 'checked', 'dispatched']) 
                            && is_null($record->parent_id)
                        )
                        ->requiresConfirmation()
                        ->form([
                            Forms\Components\Textarea::make('reason')->label('Motivo de la pausa')->required()
                        ])
                        ->action(function (Order $record, array $data) {
                            $record->update(['status' => OrderStatus::Standby]);
                            $record->children()->update(['status' => OrderStatus::Standby]);
                            Notification::make()->warning()->title('Pedido en Standby')->send();
                        }),

                    // CANCELAR PEDIDO
                    Tables\Actions\Action::make('cancelar_pedido')
                        ->label('Cancelar Pedido')
                        ->icon('heroicon-m-x-circle')
                        ->color('danger')
                        ->visible(function (Order $record) {
                            if (!is_null($record->parent_id)) return false;
                            $status = $record->status instanceof \BackedEnum ? $record->status->value : $record->status;
                            $invalidStatuses = ['dispatched', 'paid', 'cancelled'];
                            $hasValidInvoice = $record->invoices()->where('invoice_type', 'B')->exists() && !$record->isAnnulled();
                            return !in_array($status, $invalidStatuses) && !$hasValidInvoice;
                        })
                        ->requiresConfirmation()
                        ->form([
                            Forms\Components\Textarea::make('reason')->label('Motivo de cancelación')->required()
                        ])
                        ->action(function (Order $record, array $data) {
                            $record->update(['status' => OrderStatus::Cancelled]);
                            $record->children()->update(['status' => OrderStatus::Cancelled]);
                            Notification::make()->warning()->title('Pedido y derivados Cancelados')->send();
                        }),

                    // VOLVER ATRÁS
                    Tables\Actions\Action::make('volver_a_armar')
                        ->label('Volver a "Para Armar"')
                        ->icon('heroicon-m-arrow-uturn-left')
                        ->color('warning')
                        ->visible(fn (Order $record) => in_array($record->status instanceof \BackedEnum ? $record->status->value : $record->status, ['assembled', 'checked']) && is_null($record->parent_id))
                        ->requiresConfirmation()
                        ->form([
                            Forms\Components\Textarea::make('reason')->label('Motivo del retroceso')->required()
                        ])
                        ->action(function (Order $record, array $data) {
                            $record->update(['status' => OrderStatus::Processing]);
                            $record->children()->update(['status' => OrderStatus::Processing]);
                            Notification::make()->warning()->title('Pedido regresado a preparación')->send();
                        }),

                    // BORRAR INDIVIDUAL
                    Tables\Actions\DeleteAction::make()
                        ->visible(fn (Order $record) => ($record->status instanceof \BackedEnum ? $record->status->value : $record->status) === 'draft' && is_null($record->parent_id))
                        ->before(fn (Order $record) => $record->children()->delete()),

                    // ANULAR CON SELECTOR DE DESTINO
                    Tables\Actions\Action::make('anular_factura')
                        ->label('Anular (NC)')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->visible(fn (Order $record) => 
                            $record->invoices()->where('invoice_type', 'B')->exists() && 
                            !$record->isAnnulled() && 
                            is_null($record->parent_id)
                        )
                        ->requiresConfirmation()
                        ->modalHeading('¿Anular Factura en AFIP?')
                        ->form([
                            Forms\Components\Select::make('next_step')
                                ->label('¿Qué hacer con el pedido luego de la NC?')
                                ->options([
                                    'refacturar' => 'Volver a "Armado" (Para corregir mercadería y refacturar)',
                                    'cancelar' => 'Cancelar Pedido Completamente (Devolver stock)',
                                ])
                                ->default('refacturar')
                                ->required()
                        ])
                        ->action(function (Order $record, array $data) {
                            $response = \App\Services\AfipService::anular($record);
                            if ($response['success']) {
                                if ($data['next_step'] === 'cancelar') {
                                    $record->update(['status' => OrderStatus::Cancelled]);
                                    $record->children()->update(['status' => OrderStatus::Cancelled]);
                                } else {
                                    $record->update(['status' => OrderStatus::Assembled]);
                                    $record->children()->update(['status' => OrderStatus::Assembled]);
                                }
                                Notification::make()->success()->title($response['message'])->send();
                            } else {
                                Notification::make()->danger()->title('Error al Anular')->body($response['error'])->persistent()->send();
                            }
                        }),
                ]),
            ])
            ->filters([
                SelectFilter::make('status')->options(OrderStatus::class)->multiple(),
                SelectFilter::make('zone')->relationship('client.locality.zone', 'name')->multiple()->preload(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrders::route('/'),
            'create' => Pages\CreateOrder::route('/create'),
            'edit' => Pages\EditOrder::route('/{record}/edit'),
        ];
    }
}