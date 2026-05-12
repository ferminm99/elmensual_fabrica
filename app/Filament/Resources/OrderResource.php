<?php
namespace App\Filament\Resources;

use App\Enums\OrderStatus;
use App\Filament\Resources\OrderResource\Pages;
use App\Models\Article;
use App\Models\Order;
use App\Models\OrderItem; 
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
                                ->label('Cliente')
                                ->relationship('client', 'name')
                                ->searchable()
                                ->preload() 
                                ->live() 
                                ->afterStateUpdated(function (Set $set, $state) {
                                    if ($state) {
                                        $cliente = \App\Models\Client::find($state);
                                        if ($cliente && $cliente->afip_tax_condition_id) {
                                            $set('billing_type', 'fiscal'); 
                                        }
                                    }
                                })
                                ->required()
                                ->disabled(function (?Order $record) {
                                    if (!$record) return false; 
                                    $status = $record->status instanceof \BackedEnum ? $record->status->value : $record->status;
                                    return $status !== 'draft';
                                })
                                ->dehydrated(),

                            Forms\Components\Select::make('billing_type')
                                ->label('Facturación')
                                ->options(['fiscal' => 'Fiscal', 'informal' => 'Interno', 'mixed' => 'Mixto'])
                                ->required()
                                ->native(false)
                                ->disabled(function (?Order $record) {
                                    if (!$record) return false;
                                    $status = $record->status instanceof \BackedEnum ? $record->status->value : $record->status;
                                    return !in_array($status, ['draft', 'processing', 'standby']);
                                })
                                ->dehydrated(),
                           
                            Forms\Components\Select::make('status')
                                ->options(OrderStatus::class)
                                ->default(OrderStatus::Draft)
                                ->live() 
                                ->native(false) 
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
                                ->disabled(fn (Get $get) => ($get('status') instanceof \BackedEnum ? $get('status')->value : $get('status')) !== 'draft')
                                ->dehydrated(),

                            Forms\Components\Select::make('priority')
                                ->label('Prioridad')
                                ->options([1 => 'Normal', 2 => 'Alta', 3 => 'Urgente'])
                                ->default(1)->required(),
                        ])
                    ]),

                // --- NUEVA SECCIÓN: OBSERVACIONES DE LOGÍSTICA ---
                Forms\Components\Section::make('Novedades del Armador')
                    ->icon('heroicon-o-information-circle')
                    ->visible(fn (?Order $record) => $record && !empty($record->observations))
                    ->schema([
                        Forms\Components\Textarea::make('observations')
                            ->label('Mensaje dejado en la caja:')
                            ->disabled() // SOLO LECTURA
                            ->columnSpanFull()
                            ->extraAttributes(['class' => 'bg-yellow-50 text-yellow-900 border-yellow-200']),
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
                        $html = $state;
                        
                        if ($record->parent_id) {
                            $html = '<div class="flex items-center gap-2 pl-8 text-slate-500 italic">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M9 5l7 7-7 7"></path></svg> 
                                ' . $state . ' <span class="text-[9px] bg-slate-800 px-1.5 py-0.5 rounded-full border border-slate-700 not-italic font-black text-slate-400">HIJO</span></div>';
                        }
                        
                        // --- ESTO ES LO NUEVO DE LAS OBSERVACIONES ---
                        if (!empty($record->observations)) {
                            $html .= ' <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-yellow-100 text-yellow-800 border border-yellow-200" title="' . e($record->observations) . '">
                                <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path></svg> Nota Armador
                            </span>';
                        }

                        return new HtmlString($html);
                    })
                    ->description(fn (Order $record) => ($record->client->locality->name ?? '-') . ($record->client->locality?->zone ? " ({$record->client->locality->zone->name})" : '')),
                
                Tables\Columns\TextColumn::make('order_date')->date('d/m/Y')->label('Fecha')->sortable(),
                Tables\Columns\TextColumn::make('status')->badge()->label('Estado'),
                Tables\Columns\TextColumn::make('total_amount')
                    ->label('Total')
                    ->money('ARS')
                    ->weight('black')
                    ->state(function (Order $record) {
                        $status = $record->status instanceof \BackedEnum ? $record->status->value : $record->status;
                        
                        // Si el pedido ya pasó por el armador, usamos la cantidad empacada. Si no, lo original.
                        $yaArmado = in_array($status, ['assembled', 'checked', 'dispatched', 'paid', 'standby']);

                        // Consolidamos el pedido actual con sus hijos para ver el total real de toda la operación
                        $orderIds = \App\Models\Order::where('id', $record->id)
                                        ->orWhere('parent_id', $record->id)
                                        ->pluck('id')->toArray();
                        
                        return \App\Models\OrderItem::whereIn('order_id', $orderIds)->get()->sum(function ($item) use ($yaArmado) {
                            $qty = $yaArmado ? $item->packed_quantity : $item->quantity;
                            return $qty * $item->unit_price;
                        });
                    }),
            ])
            ->filtersLayout(Tables\Enums\FiltersLayout::AboveContent)
            ->headerActions([
                Tables\Actions\Action::make('configurar_cai')
                    ->label('Talonario (CAI)')
                    ->icon('heroicon-o-cog-8-tooth')
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
                        Forms\Components\Section::make('Alineación de Impresión (Hojas Físicas)')
                            ->schema([
                                Forms\Components\Toggle::make('use_preprinted_remito')
                                    ->label('Usar Talonario Pre-Impreso')
                                    ->helperText('Oculta el encabezado digital y empuja la tabla hacia abajo para imprimir sobre tus hojas membretadas.')
                                    ->live(),
                                Forms\Components\TextInput::make('preprinted_margin')
                                    ->label('Margen Superior a dejar (Centímetros)')
                                    ->numeric()
                                    ->step(0.1)
                                    ->default(12.5)
                                    ->visible(fn (Get $get) => $get('use_preprinted_remito')),
                            ])
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
                        Forms\Components\Select::make('zone_ids')->label('Zona')->options(Zone::all()->pluck('name', 'id'))->multiple()->live()->native(false),
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

                        Notification::make()->title("Lanzamiento: {$count} pedidos enviados a armado.")->success()->send();
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
                                Notification::make()->danger()->title('Acción Bloqueada')->body('Solo puedes eliminar pedidos "Borrador".')->send();
                                $action->halt(); 
                            }

                            foreach ($records as $record) {
                                $record->children()->delete();
                            }
                        }),
                ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->visible(function (Order $record) {
                        $status = $record->status instanceof \BackedEnum ? $record->status->value : $record->status;
                        return !in_array($status, ['dispatched', 'paid', 'cancelled']) && is_null($record->parent_id);
                    }),

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

                Tables\Actions\Action::make('facturar')
                    ->label('Facturar')
                    ->icon('heroicon-o-document-check')
                    ->color('success')
                    ->button()
                    ->modalWidth('7xl')
                    ->visible(function (Order $record) {
                        if (!is_null($record->parent_id)) return false;
                        $status = $record->status instanceof \BackedEnum ? $record->status->value : $record->status;
                        if (!in_array($status, ['assembled', 'standby'])) return false;
                        return !$record->children()->whereIn('status', ['draft', 'processing'])->exists();
                    })
                    ->action(function (Order $record, array $data, Tables\Actions\Action $action) {
                        $orderIds = Order::where('id', $record->id)->orWhere('parent_id', $record->id)->pluck('id')->toArray();
                        $itemsConsolidados = OrderItem::whereIn('order_id', $orderIds)->get();
                        
                        $totalPrendas = $itemsConsolidados->sum('packed_quantity');
                        $totalVenta = $itemsConsolidados->sum(fn($i) => $i->packed_quantity * $i->unit_price);

                        if ($data['billing_type'] === 'mixed' && $totalPrendas % 2 !== 0) {
                            Notification::make()->danger()->title('Cantidad Impar')->body("No podés hacer 50/50 con cantidad impar.")->persistent()->send();
                            $action->halt(); 
                        }

                        // --- 1. REVERTIMOS LA DEUDA VIEJA SI YA ESTABA FACTURADO PREVIAMENTE ---
                        if ($record->invoiced_at && $record->payment_method === 'cta_cte') {
                            $viejoTotal = $record->getOriginal('total_amount');
                            $viejoBilling = $record->getOriginal('billing_type');
                            if ($viejoBilling === 'informal') $record->client->decrement('internal_debt', $viejoTotal);
                            elseif ($viejoBilling === 'fiscal') $record->client->decrement('fiscal_debt', $viejoTotal);
                            else { $record->client->decrement('fiscal_debt', $viejoTotal / 2); $record->client->decrement('internal_debt', $viejoTotal / 2); }
                        }

                        // 2. ACTUALIZAMOS EL PEDIDO CON EL NUEVO MONTO REAL
                        $record->update([
                            'total_amount' => $totalVenta,
                            'billing_type' => $data['billing_type'],
                            'payment_method' => $data['payment_method'],
                        ]);

                        // --- 3. CARGAMOS LA NUEVA DEUDA CONSOLIDADA EXACTA ---
                        if ($data['payment_method'] === 'cta_cte') {
                            if ($data['billing_type'] === 'informal') {
                                $record->client->increment('internal_debt', $totalVenta);
                            } else if ($data['billing_type'] === 'fiscal') {
                                $record->client->increment('fiscal_debt', $totalVenta);
                            } else {
                                $record->client->increment('fiscal_debt', $totalVenta / 2);
                                $record->client->increment('internal_debt', $totalVenta / 2);
                            }
                        }
                        
                        if ($data['billing_type'] === 'informal') {
                            $record->update(['status' => OrderStatus::Checked, 'invoiced_at' => now()]);
                            $record->children()->update(['status' => OrderStatus::Checked]);
                            Notification::make()->success()->title("Venta Interna Registrada")->body("Se actualizó la deuda a $ " . number_format($totalVenta, 2))->send();
                            return; 
                        }

                        $facturaVieja = $record->invoices()->whereIn('invoice_type', ['A', 'B'])->latest()->first();
                        $estaAnulada = $facturaVieja ? $record->invoices()->where('invoice_type', 'NC')->where('parent_id', $facturaVieja->id)->exists() : false;

                        if ($facturaVieja && !$estaAnulada) {
                            $resAnular = \App\Services\AfipService::anular($record);
                            if (!$resAnular['success']) {
                                Notification::make()->danger()->title('Error anulando comprobante previo')->body($resAnular['error'])->persistent()->send();
                                $action->halt(); 
                            }
                        }

                        $response = \App\Services\AfipService::facturar($record, $data);

                        if ($response['success']) {
                            $record->update(['status' => OrderStatus::Checked, 'invoiced_at' => now()]);
                            $record->children()->update(['status' => OrderStatus::Checked]);
                            Notification::make()->success()->title("Facturación Exitosa")->send();
                        } else {
                            Notification::make()->danger()->title('Error AFIP')->body($response['error'])->persistent()->send();
                            $action->halt(); 
                        }
                    })
                    ->form(function (Order $record) {
                        $orderIds = \App\Models\Order::where('id', $record->id)->orWhere('parent_id', $record->id)->pluck('id')->toArray();
                        $itemsAgrupados = \App\Models\OrderItem::with('article')->whereIn('order_id', $orderIds)->get();
                        
                        $totalReal = $itemsAgrupados->sum(fn($i) => $i->packed_quantity * $i->unit_price);
                        $tbody = "";
                        foreach ($itemsAgrupados->groupBy('article_id') as $items) {
                            $qty = $items->sum('packed_quantity');
                            if ($qty <= 0) continue;
                            $price = $items->max('unit_price');
                            $tbody .= "<tr><td class='px-4 py-2'>{$items->first()->article->name}</td><td class='px-4 py-2 text-center'>{$qty}</td><td class='px-4 py-2 text-right'>$ " . number_format($price, 2) . "</td><td class='px-4 py-2 text-right'>$ " . number_format($qty * $price, 2) . "</td></tr>";
                        }

                        $resumenHtml = "<div class='fi-ta-content overflow-hidden rounded-xl border border-gray-200 mb-4'><table class='w-full text-sm text-left'><thead><tr class='bg-gray-50'><th>Artículo</th><th>Cant. Real</th><th>Precio</th><th>Subtotal</th></tr></thead><tbody>{$tbody}</tbody><tfoot><tr class='font-bold bg-gray-50'><td colspan='3' class='text-right p-2'>TOTAL REAL A COBRAR:</td><td class='text-right p-2 text-primary-600'>$ " . number_format($totalReal, 2, ',', '.') . "</td></tr></tfoot></table></div>";
                        
                        return [
                            Forms\Components\Placeholder::make('resumen')->label('')->content(new HtmlString($resumenHtml)),
                            Forms\Components\Grid::make(3)->schema([
                                Forms\Components\Select::make('billing_type')->label('Tipo Venta')->options(['fiscal' => 'Fiscal', 'informal' => 'Interno', 'mixed' => 'Mixto'])->default($record->billing_type)->live()->required(),
                                Forms\Components\Select::make('payment_method')->label('Método Pago')->options(['cta_cte' => 'Cta Cte', 'efectivo' => 'Efectivo', 'transferencia' => 'Transferencia', 'cheque' => 'Cheque'])->default('cta_cte')->live()->required(),
                                Forms\Components\Select::make('alt_voucher_type')->label('Tipo Comprobante')->options(['1' => 'Factura A', '6' => 'Factura B'])->visible(fn (Get $get) => $get('billing_type') !== 'informal')->required(fn (Get $get) => $get('billing_type') !== 'informal'),
                            ]),
                            
                            Forms\Components\Grid::make(3)->schema([
                                Forms\Components\Select::make('bank_id')
                                    ->label('Banco del Cheque')->options(\App\Models\Bank::pluck('name', 'id'))->searchable()->preload()
                                    ->visible(fn (Get $get) => $get('payment_method') === 'cheque')->required(fn (Get $get) => $get('payment_method') === 'cheque'),
                                Forms\Components\TextInput::make('check_number')->label('Nro. Cheque')->numeric()
                                    ->visible(fn (Get $get) => $get('payment_method') === 'cheque')->required(fn (Get $get) => $get('payment_method') === 'cheque'),
                                Forms\Components\TextInput::make('check_owner_name')->label('Nombre Emisor (Cheque)')
                                    ->visible(fn (Get $get) => $get('payment_method') === 'cheque')->required(fn (Get $get) => $get('payment_method') === 'cheque'),
                                Forms\Components\TextInput::make('check_owner_cuit')->label('CUIT Emisor (Cheque)')->numeric()
                                    ->visible(fn (Get $get) => $get('payment_method') === 'cheque')->required(fn (Get $get) => $get('payment_method') === 'cheque'),
                                Forms\Components\DatePicker::make('due_date')->label('Fecha de Cobro')
                                    ->visible(fn (Get $get) => $get('payment_method') === 'cheque')->required(fn (Get $get) => $get('payment_method') === 'cheque'),
                                
                                Forms\Components\TextInput::make('bank_name')->label('Banco Origen')->placeholder('Ej: Galicia / MP')
                                    ->visible(fn (Get $get) => $get('payment_method') === 'transferencia')->required(fn (Get $get) => $get('payment_method') === 'transferencia'),
                                Forms\Components\TextInput::make('transaction_id')->label('Nro. Comprobante / Ref.')
                                    ->visible(fn (Get $get) => $get('payment_method') === 'transferencia')->required(fn (Get $get) => $get('payment_method') === 'transferencia'),
                            ]),
                        ];
                    }),

                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('print_remitos')->label('Remitos (x3)')->icon('heroicon-o-document-duplicate')->color('gray')->visible(fn (Order $record) => $record->billing_type !== 'informal' && in_array($record->status instanceof \BackedEnum ? $record->status->value : $record->status, ['checked', 'dispatched', 'paid']) && is_null($record->parent_id))->url(fn (Order $record) => url('/admin/orders/'.$record->id.'/remito'))->openUrlInNewTab(),
                    Tables\Actions\Action::make('print_presupuesto')->label('Boleta Interna (X)')->icon('heroicon-o-document-currency-dollar')->color('warning')->visible(fn (Order $record) => in_array($record->status instanceof \BackedEnum ? $record->status->value : $record->status, ['checked', 'dispatched', 'paid']) && is_null($record->parent_id))->url(fn (Order $record) => url('/admin/orders/'.$record->id.'/presupuesto'))->openUrlInNewTab(),
                    
                    Tables\Actions\Action::make('descargar_factura')
                        ->label('Facturas AFIP')->icon('heroicon-o-document-text')->color('success')->visible(fn (Order $record) => $record->invoices()->where('invoice_type', 'B')->exists() && is_null($record->parent_id))
                        ->url(function (Order $record) {
                            $invoices = $record->invoices()->where('invoice_type', 'B')->get();
                            if ($invoices->count() === 1) return route('order.invoice.download', ['order' => $record->id, 'type' => 'B', 'invoice_id' => $invoices->first()->id]);
                            return null;
                        })
                        ->openUrlInNewTab()
                        ->form(function (Order $record) {
                            $invoices = $record->invoices()->where('invoice_type', 'B')->get();
                            if ($invoices->count() <= 1) return [];
                            return [Forms\Components\Select::make('invoice_id')->label('Seleccione la factura')->options($invoices->mapWithKeys(fn($i) => [$i->id => "Factura {$i->number}"]))->native(false)->required()];
                        })
                        ->action(function (Order $record, array $data, $livewire) {
                            $url = route('order.invoice.download', ['order' => $record->id, 'invoice_id' => $data['invoice_id']]);
                            $livewire->js("window.open('{$url}', '_blank')");
                        }),

                    Tables\Actions\Action::make('descargar_nc')
                        ->label('Notas de Crédito')->icon('heroicon-o-document-minus')->color('danger')->visible(fn (Order $record) => $record->invoices()->where('invoice_type', 'NC')->exists() && is_null($record->parent_id))
                        ->form(function (Order $record) {
                            $ncs = $record->invoices()->where('invoice_type', 'NC')->get();
                            if ($ncs->count() <= 1) return [];
                            return [Forms\Components\Select::make('invoice_id')->label('Seleccione la Nota de Crédito')->options($ncs->mapWithKeys(fn($nc) => [$nc->id => "NC {$nc->number}"]))->native(false)->required()];
                        })
                        ->action(function (Order $record, array $data) {
                            $invoiceId = $data['invoice_id'] ?? $record->invoices()->where('invoice_type', 'NC')->first()->id;
                            return redirect()->route('order.invoice.download', ['order' => $record->id, 'type' => 'NC', 'invoice_id' => $invoiceId]);
                        }),
                ])
                ->label('🖨️ Documentos')->icon('heroicon-m-printer')->button()->color('gray')
                ->visible(function (Order $record) {
                    if (!is_null($record->parent_id)) return false;
                    $status = $record->status instanceof \BackedEnum ? $record->status->value : $record->status;
                    return in_array($status, ['checked', 'dispatched', 'paid']) || $record->invoices()->exists();
                }),
                
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('poner_en_standby')
                        ->label('Pausar (Standby)')
                        ->icon('heroicon-m-pause-circle')
                        ->color('warning')
                        ->visible(fn (Order $record) => in_array($record->status instanceof \BackedEnum ? $record->status->value : $record->status, ['assembled', 'checked', 'dispatched']) && is_null($record->parent_id))
                        ->requiresConfirmation()
                        ->form([Forms\Components\Textarea::make('reason')->label('Motivo de la pausa')->required()])
                        ->action(function (Order $record, array $data) {
                            // SI TENIA DEUDA CARGADA, SE LA DECONTAMOS AL CLIENTE
                            if ($record->invoiced_at && $record->payment_method === 'cta_cte') {
                                $total = $record->total_amount;
                                if ($record->billing_type === 'informal') $record->client->decrement('internal_debt', $total);
                                elseif ($record->billing_type === 'fiscal') $record->client->decrement('fiscal_debt', $total);
                                else { $record->client->decrement('fiscal_debt', $total / 2); $record->client->decrement('internal_debt', $total / 2); }
                                $record->update(['invoiced_at' => null]);
                            }

                            $record->update(['status' => OrderStatus::Standby]);
                            $record->children()->update(['status' => OrderStatus::Standby]);
                            Notification::make()->warning()->title('Pedido en Standby y deuda revertida')->send();
                        }),

                    Tables\Actions\Action::make('cancelar_pedido')
                        ->label('Cancelar Pedido')
                        ->icon('heroicon-m-x-circle')
                        ->color('danger')
                        ->visible(function (Order $record) {
                            if (!is_null($record->parent_id)) return false;
                            $status = $record->status instanceof \BackedEnum ? $record->status->value : $record->status;
                            return !in_array($status, ['paid', 'cancelled']);
                        })
                        ->requiresConfirmation()
                        ->form([Forms\Components\Textarea::make('reason')->label('Motivo de cancelación')->required()])
                        ->action(function (Order $record, array $data) {
                            $facturasVivas = $record->invoices()->where('invoice_type', 'B')->get()->filter(function($factura) use ($record) {
                                return !$record->invoices()->where('invoice_type', 'NC')->where('parent_id', $factura->id)->exists();
                            });

                            if ($facturasVivas->isNotEmpty()) {
                                Notification::make()->warning()->title("Anulando comprobantes en AFIP...")->send();
                                foreach ($facturasVivas as $factura) {
                                    $resAFIP = \App\Services\AfipService::anularFactura($factura);
                                    if (!$resAFIP['success']) {
                                        Notification::make()->danger()->title("Error AFIP: Factura {$factura->number}")->body($resAFIP['error'])->persistent()->send();
                                        return;
                                    }
                                }
                            }

                            // SI TENIA DEUDA CARGADA, SE LA DECONTAMOS AL CLIENTE
                            if ($record->invoiced_at && $record->payment_method === 'cta_cte') {
                                $total = $record->total_amount;
                                if ($record->billing_type === 'informal') $record->client->decrement('internal_debt', $total);
                                elseif ($record->billing_type === 'fiscal') $record->client->decrement('fiscal_debt', $total);
                                else { $record->client->decrement('fiscal_debt', $total / 2); $record->client->decrement('internal_debt', $total / 2); }
                                $record->update(['invoiced_at' => null]);
                            }

                            $record->update(['status' => OrderStatus::Cancelled]);
                            $record->children()->update(['status' => OrderStatus::Cancelled]);
                            Notification::make()->success()->title('Pedido y facturas anulados. Deuda revertida.')->send();
                        }),

                    Tables\Actions\Action::make('volver_a_armar')
                        ->label('Volver a "Para Armar"')
                        ->icon('heroicon-m-arrow-uturn-left')
                        ->color('warning')
                        ->visible(fn (Order $record) => in_array($record->status instanceof \BackedEnum ? $record->status->value : $record->status, ['assembled', 'checked']) && is_null($record->parent_id))
                        ->requiresConfirmation()
                        ->form([Forms\Components\Textarea::make('reason')->label('Motivo del retroceso')->required()])
                        ->action(function (Order $record, array $data) {
                            // SI TENIA DEUDA CARGADA, SE LA DECONTAMOS AL CLIENTE
                            if ($record->invoiced_at && $record->payment_method === 'cta_cte') {
                                $total = $record->total_amount;
                                if ($record->billing_type === 'informal') $record->client->decrement('internal_debt', $total);
                                elseif ($record->billing_type === 'fiscal') $record->client->decrement('fiscal_debt', $total);
                                else { $record->client->decrement('fiscal_debt', $total / 2); $record->client->decrement('internal_debt', $total / 2); }
                                $record->update(['invoiced_at' => null]);
                            }

                            $record->update(['status' => OrderStatus::Processing]);
                            $record->children()->update(['status' => OrderStatus::Processing]);
                            Notification::make()->warning()->title('Pedido regresado a preparación y deuda revertida')->send();
                        }),

                    Tables\Actions\DeleteAction::make()
                        ->visible(fn (Order $record) => ($record->status instanceof \BackedEnum ? $record->status->value : $record->status) === 'draft' && is_null($record->parent_id))
                        ->before(fn (Order $record) => $record->children()->delete()),

                    Tables\Actions\Action::make('anular_factura')
                        ->label('Anular (NC)')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->visible(fn (Order $record) => $record->invoices()->where('invoice_type', 'B')->exists() && !$record->isAnnulled() && is_null($record->parent_id))
                        ->requiresConfirmation()
                        ->modalHeading('¿Anular Factura en AFIP?')
                        ->form([
                            Forms\Components\Select::make('next_step')
                                ->label('¿Qué hacer con el pedido luego de la NC?')
                                ->options([
                                    'refacturar' => 'Volver a "Armado"',
                                    'cancelar' => 'Cancelar Pedido Completamente',
                                ])
                                ->native(false) 
                                ->default('refacturar')
                                ->required()
                        ])
                        ->action(function (Order $record, array $data) {
                            $response = \App\Services\AfipService::anular($record);
                            if ($response['success']) {
                                // SI TENIA DEUDA CARGADA, SE LA DECONTAMOS AL CLIENTE
                                if ($record->invoiced_at && $record->payment_method === 'cta_cte') {
                                    $total = $record->total_amount;
                                    if ($record->billing_type === 'informal') $record->client->decrement('internal_debt', $total);
                                    elseif ($record->billing_type === 'fiscal') $record->client->decrement('fiscal_debt', $total);
                                    else { $record->client->decrement('fiscal_debt', $total / 2); $record->client->decrement('internal_debt', $total / 2); }
                                    $record->update(['invoiced_at' => null]);
                                }

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