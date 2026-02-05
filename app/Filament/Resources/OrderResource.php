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
                    ->modalWidth('5xl')
                    ->visible(fn (Order $record) => in_array($record->status->value, ['assembled', 'standby']))
                    ->form(function (Order $record) {
                        $itemsAgrupados = $record->items()
                            ->select('article_id', DB::raw('SUM(packed_quantity) as total_qty'), DB::raw('AVG(unit_price) as price'))
                            ->groupBy('article_id')
                            ->having('total_qty', '>', 0)
                            ->get();

                        $totalCostoPedido = 0;
                        $resumenHtml = "<div class='p-4 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 shadow-sm'>";
                        $resumenHtml .= "<table class='w-full text-sm text-left text-gray-700 dark:text-gray-300'>";
                        $resumenHtml .= "<thead class='text-xs uppercase bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400'>
                                            <tr>
                                                <th class='px-3 py-2 rounded-l-lg'>Artículo / Código</th>
                                                <th class='px-3 py-2 text-center'>Cant.</th>
                                                <th class='px-3 py-2 text-right rounded-r-lg'>Subtotal</th>
                                            </tr>
                                        </thead><tbody>";
                        foreach ($itemsAgrupados as $item) {
                            $sub = $item->total_qty * $item->price;
                            $totalCostoPedido += $sub;
                            $resumenHtml .= "<tr class='border-b border-gray-100 dark:border-gray-800/50 hover:bg-gray-50 dark:hover:bg-gray-800/30 transition-colors'>
                                                <td class='px-3 py-2 font-medium text-gray-900 dark:text-white'>{$item->article->code} - {$item->article->name}</td>
                                                <td class='px-3 py-2 text-center'>{$item->total_qty}</td>
                                                <td class='px-3 py-2 text-right font-mono'>$" . number_format($sub, 2) . "</td>
                                            </tr>";
                        }
                        $resumenHtml .= "</tbody><tfoot>
                                            <tr class='font-bold text-gray-900 dark:text-white uppercase'>
                                                <td class='px-3 pt-4 font-black'>TOTAL CONSOLIDADO</td>
                                                <td class='px-3 pt-4 text-center'>".$itemsAgrupados->sum('total_qty')."</td>
                                                <td class='px-3 pt-4 text-right text-lg text-success-600 dark:text-success-400'>$" . number_format($totalCostoPedido, 2) . "</td>
                                            </tr>
                                        </tfoot></table></div>";

                        return [
                            Forms\Components\Placeholder::make('resumen_carga')
                                ->label('Detalle de Mercadería Real')
                                ->content(new HtmlString($resumenHtml)),

                            // SECCIÓN 1: COMPROBANTE (2 columnas internas alineadas)
                            Forms\Components\Section::make('Configuración del Comprobante')
                                ->icon('heroicon-m-document-text')
                                ->schema([
                                    Forms\Components\Grid::make(2)->schema([
                                        Forms\Components\Select::make('billing_type')
                                            ->label('Tipo Facturación')
                                            ->options(['fiscal' => 'Fiscal (AFIP)', 'informal' => 'Interno (Remito)', 'mixed' => 'Mixto (Paridad)'])
                                            ->default($record->client->billing_type ?? 'mixed')
                                            ->required()->live(),
                                        Forms\Components\Placeholder::make('info_afip')
                                            ->label('Número de Factura')
                                            ->content('Se obtendrá automáticamente de AFIP al confirmar'),
                                    ]),
                                ]),

                            // SECCIÓN 2: PAGO (2 columnas internas alineadas)
                            Forms\Components\Section::make('Información de Pago')
                                ->icon('heroicon-m-banknotes')
                                ->schema([
                                    Forms\Components\Grid::make(2)->schema([
                                        Forms\Components\Select::make('payment_method')
                                            ->label('Método de Pago')
                                            ->options(['cta_cte' => 'Cuenta Corriente', 'efectivo' => 'Efectivo', 'transferencia' => 'Transferencia', 'cheque' => 'Cheque'])
                                            ->default($record->client->last_payment_method ?? 'cta_cte')
                                            ->required()->live(),
                                        
                                        // Campos de Transferencia alineados
                                        Forms\Components\TextInput::make('bank_name')
                                            ->label('Banco Origen')
                                            ->placeholder('Ej: Galicia / MP')
                                            ->hidden(fn (Get $get) => $get('payment_method') !== 'transferencia')
                                            ->required(fn (Get $get) => $get('payment_method') === 'transferencia'),
                                        Forms\Components\TextInput::make('transaction_id')
                                            ->label('ID Operación / Ref.')
                                            ->hidden(fn (Get $get) => $get('payment_method') !== 'transferencia')
                                            ->required(fn (Get $get) => $get('payment_method') === 'transferencia'),

                                        // Campos de Cheque alineados
                                        Forms\Components\TextInput::make('check_number')
                                            ->label('Número de Cheque')
                                            ->hidden(fn (Get $get) => $get('payment_method') !== 'cheque')
                                            ->required(fn (Get $get) => $get('payment_method') === 'cheque'),
                                        Forms\Components\DatePicker::make('due_date')
                                            ->label('Fecha de Vencimiento')
                                            ->hidden(fn (Get $get) => $get('payment_method') !== 'cheque')
                                            ->required(fn (Get $get) => $get('payment_method') === 'cheque'),
                                    ]),
                                ]),
                        ];
                    })
                    ->action(function (Order $record, array $data) {
                        try {
                            // 1. Instancia limpia (aprovechando los SYMLINKS que creamos)
                            // No necesitamos pasar rutas absolutas porque la librería las busca en su carpeta interna
                            // y los symlinks la redirigen a storage/app/afip/
                            $afip = new \Afip([
                                'CUIT'        => 30633784104, // CUIT de la Fábrica (Representado)
                                'production'  => false,
                                'cert'        => 'cert',      // Nombre del symlink
                                'key'         => 'key',       // Nombre del symlink
                                'token_dir'   => storage_path('app/afip/xml/'),
                            ]);

                            // 2. Seteo de delegación (Tu CUIT como representante)
                            $afip->owner_CUIT = 20219177713;

                            // 3. Cálculos de IVA (Precisión AFIP)
                            $total = round($record->total_amount, 2);
                            $neto  = round($total / 1.21, 2);
                            $iva   = round($total - $neto, 2);

                            // 4. Obtener próximo comprobante
                            $last_voucher = $afip->ElectronicBilling->GetLastVoucher(1, 6);
                            $next_voucher = $last_voucher + 1;

                            // 5. Preparar Request
                            $request_data = [
                                'CantReg'      => 1,
                                'PtoVta'       => 1,
                                'CbteTipo'     => 6, // Factura B
                                'Concepto'     => 1, // Productos
                                'DocTipo'      => 99, // Consumidor Final
                                'DocNro'       => 0,
                                'CbteDesde'    => $next_voucher,
                                'CbteHasta'    => $next_voucher,
                                'CbteFch'      => date('Ymd'),
                                'ImpTotal'     => $total,
                                'ImpTotConc'   => 0,
                                'ImpNeto'      => $neto,
                                'ImpOpEx'      => 0,
                                'ImpIVA'       => $iva,
                                'ImpTrib'      => 0,
                                'MonId'        => 'PES',
                                'MonCotiz'     => 1,
                                'Iva'          => [
                                    [
                                        'Id'      => 5, // 21%
                                        'BaseImp' => $neto,
                                        'Importe' => $iva
                                    ]
                                ]
                            ];

                            // 6. Ejecución
                            $res = $afip->ElectronicBilling->CreateVoucher($request_data);
                            
                            $cae = $res['CAE'];
                            $vtocae = $res['CAEFchVto'];

                            // 7. Persistencia
                            DB::transaction(function () use ($record, $data, $next_voucher, $cae, $vtocae) {
                                $record->client->update([
                                    'billing_type' => $data['billing_type'], 
                                    'last_payment_method' => $data['payment_method']
                                ]);

                                $record->invoice()->create([
                                    'number' => '0001-' . str_pad($next_voucher, 8, '0', STR_PAD_LEFT),
                                    'type' => $data['billing_type'],
                                    'total_amount' => $record->total_amount,
                                    'status' => 'issued',
                                    'cae' => $cae,
                                    'due_date' => $vtocae,
                                    'notes' => "Factura generada vía AFIP (Homologación). Pago: {$data['payment_method']}"
                                ]);

                                $record->update(['status' => OrderStatus::Checked]);
                            });

                            Notification::make()->success()->title("Factura B #$next_voucher generada con éxito")->send();

                        } catch (\Exception $e) {
                            \Log::error("ERROR AFIP ERP: " . $e->getMessage());
                            Notification::make()
                                ->danger()
                                ->title('Error AFIP')
                                ->body($e->getMessage())
                                ->persistent()
                                ->send();
                        }
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