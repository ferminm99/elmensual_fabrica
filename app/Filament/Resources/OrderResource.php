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
                                ->disabled(fn (Get $get) => $get('status') !== 'draft'),

                            Forms\Components\Select::make('billing_type')
                                ->label('Facturación')
                                ->options(['fiscal' => 'Fiscal', 'informal' => 'Interno', 'mixed' => 'Mixto'])
                                ->default('fiscal')
                                ->required()
                                ->disabled(fn (Get $get) => !in_array($get('status'), ['draft', 'processing', 'standby'])),
                           
                            Forms\Components\Select::make('status')
                                ->options(OrderStatus::class)
                                ->live()
                                ->required()
                                ->disableOptionWhen(function ($value, ?Order $record, Get $get) {
                                    if (!$record) return false;
                                    
                                    $dbStatus = $record->getOriginal('status');
                                    if ($dbStatus instanceof \BackedEnum) $dbStatus = $dbStatus->value;

                                    // --- REGLA: MIXTO SIEMPRE PAR ---
                                    // Si el modo de facturación es mixto, el total de prendas armadas debe ser par.
                                    if ($get('billing_type') === 'mixed') {
                                        $totalArmado = $record->items->sum('packed_quantity');
                                        if ($totalArmado % 2 !== 0 && in_array($value, ['checked', 'dispatched', 'paid'])) {
                                            return true; // Bloquea avanzar si es impar
                                        }
                                    }

                                    // --- REGLA: ENTRADA A STANDBY ---
                                    if ($value === 'standby') {
                                        $estadosHabilitantes = ['assembled', 'checked', 'dispatched'];
                                        return !in_array($dbStatus, $estadosHabilitantes);
                                    }

                                    // --- REGLA: OBLIGAR FACTURACIÓN (EL CAMBIO CLAVE) ---
                                    // Si el usuario intenta elegir Verificado o superior manualmente...
                                    if (in_array($value, ['checked', 'dispatched', 'paid'])) {
                                        // Si el pedido NO tiene factura registrada, BLOQUEAMOS el select manual.
                                        // Esto obliga a usar el botón "Facturar" de la tabla.
                                        if (!$record->invoice()->exists() && $value !== $dbStatus) {
                                            return true;
                                        }
                                    }

                                    // --- REGLA: STANDBY HACIA ADELANTE ---
                                    if ($dbStatus === 'standby') {
                                        $permitidos = ['dispatched', 'paid', 'cancelled', 'standby'];
                                        if (!in_array($value, $permitidos)) return true;

                                        $hijosPendientes = $record->children()
                                            ->whereNotIn('status', ['assembled', 'checked', 'dispatched', 'paid', 'cancelled'])
                                            ->exists();
                                        if ($hijosPendientes && in_array($value, ['dispatched', 'paid'])) return true;
                                    }

                                    // --- REGLA: NO RETORNO ---
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
                                ->disabled(fn (Get $get) => $get('status') !== 'draft'),

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
                            ->visible(fn (Get $get) => in_array($get('status'), ['draft', 'standby']))
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
                                $target = ($get('status') === 'standby') ? 'child_groups' : 'article_groups';
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
                        $count = Order::where('status', OrderStatus::Draft)->whereHas('client', fn($q) => $q->whereIn('locality_id', $data['locality_ids']))->update(['status' => OrderStatus::Processing]);
                        Notification::make()->title("Lanzamiento: {$count} pedidos enviados a armado.")->success()->send();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('changeStatus')
                        ->label('Cambiar Estado')
                        ->icon('heroicon-o-arrow-path')
                        ->form([
                            Forms\Components\Select::make('status')
                                ->options(OrderStatus::class)->required(),
                        ])
                        ->action(fn (Collection $records, array $data) => $records->each->update(['status' => $data['status']])),
                ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('descargar_factura')
                    ->label('Ver PDF')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->visible(fn (Order $record) => $record->invoice()->exists())
                    ->url(fn (Order $record) => route('order.invoice.download', $record->id))
                    ->openUrlInNewTab(),
                // app/Filament/Resources/OrderResource.php

                Tables\Actions\Action::make('facturar')
                    ->label('Facturar')
                    ->icon('heroicon-o-document-check')
                    ->color('success')
                    ->visible(fn (Order $record) => in_array($record->status->value, ['assembled', 'standby']))
                    ->form(function (Order $record) {
                        // Agrupamos artículos para el resumen (sin repetir por color)
                        $itemsAgrupados = $record->items()
                            ->select('article_id', DB::raw('SUM(packed_quantity) as total_qty'))
                            ->groupBy('article_id')
                            ->having('total_qty', '>', 0)
                            ->get();

                        $resumen = $itemsAgrupados->map(fn($i) => "- {$i->article->name}: {$i->total_qty} un.")->implode("<br>");
                        $totalPrendas = $itemsAgrupados->sum('total_qty');

                        return [
                            Forms\Components\Placeholder::make('resumen_carga')
                                ->label('Detalle Consolidado')
                                ->content(new HtmlString($resumen . "<br><b>Total: {$totalPrendas} prendas</b>")),

                            Forms\Components\Grid::make(2)->schema([
                                Forms\Components\Select::make('billing_type')
                                    ->label('Tipo de Comprobante')
                                    ->options(['fiscal' => 'Factura A/B', 'informal' => 'Remito Interno', 'mixed' => 'Mixto (PAR)'])
                                    ->default($record->client->billing_type ?? 'mixed')
                                    ->required()->live(),

                                Forms\Components\Select::make('payment_method')
                                    ->label('Método de Pago')
                                    ->options([
                                        'cta_cte' => 'Cuenta Corriente',
                                        'efectivo' => 'Efectivo',
                                        'transferencia' => 'Transferencia',
                                        'cheque' => 'Cheque',
                                    ])
                                    ->default($record->client->last_payment_method ?? 'cta_cte')
                                    ->required()->live(),
                            ]),

                            // SECCIÓN DINÁMICA DE PAGOS
                            Forms\Components\Section::make('Datos del Pago')
                                ->visible(fn (Get $get) => in_array($get('payment_method'), ['transferencia', 'cheque']))
                                ->columns(2)
                                ->schema([
                                    // Campos para Transferencia
                                    Forms\Components\TextInput::make('bank_name')
                                        ->label('Banco Origen')
                                        ->placeholder('Ej: Galicia / Mercado Pago')
                                        ->visible(fn (Get $get) => $get('payment_method') === 'transferencia')
                                        ->required(fn (Get $get) => $get('payment_method') === 'transferencia'),
                                    
                                    Forms\Components\TextInput::make('cbu_alias')
                                        ->label('CBU o Alias de quien envía')
                                        ->visible(fn (Get $get) => $get('payment_method') === 'transferencia')
                                        ->required(fn (Get $get) => $get('payment_method') === 'transferencia'),

                                    Forms\Components\TextInput::make('transaction_id')
                                        ->label('ID Transacción / Nro Operación')
                                        ->visible(fn (Get $get) => $get('payment_method') === 'transferencia')
                                        ->required(fn (Get $get) => $get('payment_method') === 'transferencia'),

                                    // Campos para Cheque (Igual que en tu módulo de cheques)
                                    Forms\Components\TextInput::make('check_number')
                                        ->label('Nro de Cheque')
                                        ->visible(fn (Get $get) => $get('payment_method') === 'cheque')
                                        ->required(fn (Get $get) => $get('payment_method') === 'cheque'),

                                    Forms\Components\DatePicker::make('due_date')
                                        ->label('Fecha de Vencimiento')
                                        ->visible(fn (Get $get) => $get('payment_method') === 'cheque')
                                        ->required(fn (Get $get) => $get('payment_method') === 'cheque'),
                                ]),

                            Forms\Components\Section::make('Facturación')
                                ->description('Simulación de AFIP (Próximamente automático)')
                                ->schema([
                                    Forms\Components\TextInput::make('manual_invoice_number')
                                        ->label('Nro Factura (Para testeo manual ahora)')
                                        ->helperText('Cuando integremos AFIP, este campo desaparecerá y será automático.'),
                                ])
                        ];
                    })
                    ->action(function (Order $record, array $data) {
                        $totalPrendas = $record->items->sum('packed_quantity');

                        if ($data['billing_type'] === 'mixed' && $totalPrendas % 2 !== 0) {
                            Notification::make()->danger()->title('Error: Cantidad Impar')->body('Mixto requiere número par.')->send();
                            return;
                        }

                        DB::transaction(function () use ($record, $data) {
                            // Guardar en el cliente para la próxima
                            $record->client->update([
                                'billing_type' => $data['billing_type'],
                                'last_payment_method' => $data['payment_method']
                            ]);

                            // Creamos la Factura en DB
                            $record->invoice()->create([
                                'number' => $data['manual_invoice_number'] ?? 'PENDIENTE-AFIP',
                                'type' => $data['billing_type'],
                                'total_amount' => $record->total_amount,
                                'status' => 'issued',
                                'notes' => match($data['payment_method']) {
                                    'transferencia' => "Transf: {$data['bank_name']} | CBU: {$data['cbu_alias']} | ID: {$data['transaction_id']}",
                                    'cheque' => "Cheque Nro: {$data['check_number']} | Vence: {$data['due_date']}",
                                    default => "Pago: {$data['payment_method']}"
                                }
                            ]);

                            $record->update(['status' => OrderStatus::Checked]);
                        });

                        Notification::make()->success()->title('Facturado y Verificado').send();
                    })
                    ->requiresConfirmation()
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