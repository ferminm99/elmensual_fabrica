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
                                ->default(OrderStatus::Draft) // IMPORTANTE: Arranca en Borrador
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
                                        // Usamos total_fiscal para validar
                                        if (!$record->invoice()->where('total_fiscal', '>', 0)->exists() && $value !== $dbStatus) {
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
                    ->label('Ver Factura')
                    ->icon('heroicon-o-document-text')
                    ->color('gray')
                    ->visible(fn (Order $record) => $record->invoice()->where('total_fiscal', '>', 0)->exists())
                    ->url(fn (Order $record) => route('order.invoice.download', $record->id))
                    ->openUrlInNewTab(),

                Tables\Actions\Action::make('facturar')
                    ->label('Facturar')
                    ->icon('heroicon-o-document-check')
                    ->color('success')
                    ->modalWidth('5xl')
                    ->visible(fn (Order $record) => in_array($record->status->value, ['assembled', 'standby']) && !$record->invoice()->where('total_fiscal', '>', 0)->exists())
                    ->form(function (Order $record) {
                        $itemsAgrupados = $record->items()
                            ->select('article_id', DB::raw('SUM(packed_quantity) as total_qty'), DB::raw('AVG(unit_price) as price'))
                            ->groupBy('article_id')->having('total_qty', '>', 0)->get();

                        $totalCostoPedido = $itemsAgrupados->sum(fn($i) => $i->total_qty * $i->price);
                        
                        $resumenHtml = "<div class='p-4 border rounded'><strong>Total a Facturar:</strong> $".number_format($totalCostoPedido, 2)."</div>";

                        return [
                            Forms\Components\Placeholder::make('resumen_carga')
                                ->label('Detalle')
                                ->content(new HtmlString($resumenHtml)),

                            Forms\Components\Section::make('Configuración')->schema([
                                Forms\Components\Grid::make(2)->schema([
                                    Forms\Components\Select::make('billing_type')
                                        ->label('Tipo Facturación')
                                        ->options(['fiscal' => 'Fiscal', 'informal' => 'Interno', 'mixed' => 'Mixto'])
                                        ->default($record->client->billing_type ?? 'mixed')
                                        ->required()->live(),
                                    Forms\Components\Placeholder::make('info_afip')->content('Conexión AFIP'),
                                ]),
                            ]),

                            Forms\Components\Section::make('Información de Pago')->schema([
                                Forms\Components\Grid::make(2)->schema([
                                    Forms\Components\Select::make('payment_method')
                                        ->options(['cta_cte' => 'Cta Cte', 'efectivo' => 'Efectivo', 'transferencia' => 'Transferencia', 'cheque' => 'Cheque'])
                                        ->default($record->client->last_payment_method ?? 'cta_cte')
                                        ->required()->live(),
                                    
                                    Forms\Components\TextInput::make('bank_name')->label('Banco Origen')->placeholder('Ej: Galicia / MP')
                                        ->hidden(fn (Get $get) => $get('payment_method') !== 'transferencia')
                                        ->required(fn (Get $get) => $get('payment_method') === 'transferencia'),
                                    Forms\Components\TextInput::make('transaction_id')->label('Ref.')
                                        ->hidden(fn (Get $get) => $get('payment_method') !== 'transferencia')
                                        ->required(fn (Get $get) => $get('payment_method') === 'transferencia'),
                                    Forms\Components\TextInput::make('check_number')->label('Nro Cheque')
                                        ->hidden(fn (Get $get) => $get('payment_method') !== 'cheque')
                                        ->required(fn (Get $get) => $get('payment_method') === 'cheque'),
                                    Forms\Components\DatePicker::make('due_date')->label('Vencimiento')
                                        ->hidden(fn (Get $get) => $get('payment_method') !== 'cheque')
                                        ->required(fn (Get $get) => $get('payment_method') === 'cheque'),
                                ]),
                            ]),
                        ];
                    })
                    ->action(function (Order $record, array $data) {
                        $response = \App\Services\AfipService::facturar($record, $data);
                        if ($response['success']) {
                            Notification::make()->success()->title($response['message'])->send();
                        } else {
                            Notification::make()->danger()->title('Error AFIP')->body($response['error'])->persistent()->send();
                        }
                    })
                    ->requiresConfirmation(),

                Tables\Actions\Action::make('anular_factura')
                    ->label('Anular (NC)')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (Order $record) => $record->invoice()->where('total_fiscal', '>', 0)->exists())
                    ->requiresConfirmation()
                    ->modalHeading('¿Anular Factura en AFIP?')
                    ->modalSubmitActionLabel('Sí, Generar Nota de Crédito')
                    ->action(function (Order $record) {
                        $response = \App\Services\AfipService::anular($record);
                        if ($response['success']) {
                            Notification::make()->success()->title($response['message'])->send();
                        } else {
                            Notification::make()->danger()->title('Error al Anular')->body($response['error'])->persistent()->send();
                        }
                    }),
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