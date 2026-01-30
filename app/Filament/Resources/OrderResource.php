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
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;

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
                            // 1. Cliente
                            Forms\Components\Select::make('client_id')
                                ->disabled(fn (?Order $record) => $record && $record->status !== OrderStatus::Draft)
                                ->relationship('client', 'name')
                                ->searchable()
                                ->required(),

                            // 2. Facturación
                            Forms\Components\Select::make('billing_type')
                                ->label('Facturación')
                                ->options(['fiscal' => 'Fiscal', 'informal' => 'Interno', 'mixed' => 'Mixto'])
                                ->default('fiscal')
                                ->required(),
                           
                            // 3. Estado (Tu lógica de pesos recuperada)
                            Forms\Components\Select::make('status')
                                ->label('Estado')
                                ->options(OrderStatus::class)
                                ->live()
                                ->disableOptionWhen(function ($value, ?Order $record) {
                                    if (!$record) return false;
                                    $currentStatus = $record->status instanceof OrderStatus ? $record->status->value : $record->status;
                                    
                                    // Bloqueo de facturación si hay hijos pendientes
                                    if ($record->parent_id === null && in_array($value, ['dispatched', 'delivered', 'paid'])) {
                                        if ($record->children()->whereNotIn('status', [OrderStatus::Assembled, OrderStatus::Checked, OrderStatus::Cancelled])->exists()) return true;
                                    }

                                    $weights = ['draft' => 1, 'processing' => 2, 'assembled' => 3, 'checked' => 4, 'standby' => 4, 'dispatched' => 5, 'delivered' => 6, 'paid' => 7, 'cancelled' => 0];
                                    $cw = $weights[$currentStatus] ?? 0;
                                    $tw = $weights[$value] ?? 0;
                                    if ($cw >= 3 && $value !== 'cancelled' && $tw < $cw) return true;
                                    return false;
                                })->required(),

                            Forms\Components\DatePicker::make('order_date')->label('Fecha')->default(now())->required(),

                            Forms\Components\Select::make('priority')
                                ->label('Prioridad')
                                ->options([1 => 'Normal', 2 => 'Alta', 3 => 'Urgente'])
                                ->default(1)->required(),
                        ])
                    ]),

                // LA MATRIZ INDUSTRIAL (Integrada)
                Forms\Components\Section::make('Detalle de Mercadería')
                    ->headerActions([
                        Forms\Components\Actions\Action::make('add_article')
                            ->label('Añadir Artículo')
                            ->color('success')
                            ->icon('heroicon-m-plus')
                            ->visible(fn (Get $get) => in_array($get('status'), ['draft', 'standby']))
                            ->form([
                                Forms\Components\Select::make('article_id')
                                    ->label('Artículo')
                                    ->options(fn(Get $get) => Article::whereNotIn('id', collect($get('article_groups'))->pluck('article_id')->merge(collect($get('child_groups'))->pluck('article_id'))->toArray())
                                        ->get()->mapWithKeys(fn($a) => [$a->id => "{$a->code} - {$a->name}"]))
                                    ->searchable()->required()
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
                            ->registerActions([
                                Forms\Components\Actions\Action::make('removeArticle')
                                    ->action(function (array $arguments, Set $set, Get $get) {
                                        $current = $get('article_groups'); unset($current[$arguments['groupKey']]); $set('article_groups', $current);
                                    }),
                                Forms\Components\Actions\Action::make('removeChildGroup')
                                    ->action(function (array $arguments, Set $set, Get $get) {
                                        $groups = $get('child_groups'); unset($groups[$arguments['key']]); $set('child_groups', $groups);
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
                Tables\Columns\TextColumn::make('client.name')->label('Cliente / Zona')->weight('bold')->searchable()
                    ->description(fn (Order $record) => ($record->client->locality->name ?? '-') . ($record->client->locality?->zone ? " ({$record->client->locality->zone->name})" : '')),
                Tables\Columns\TextColumn::make('order_date')->date('d/m/Y')->label('Fecha')->sortable(),
                Tables\Columns\TextColumn::make('status')->badge()->label('Estado'),
                Tables\Columns\TextColumn::make('total_amount')->money('ARS')->label('Total')->weight('black'),
            ])
            ->headerActions([
                // RECUPERADO: El Lanzador Logístico Masivo
                Tables\Actions\Action::make('global_send_to_packing')
                    ->label('Lanzador Logístico')
                    ->icon('heroicon-o-rocket-launch')
                    ->color('success')
                    ->form([
                        Forms\Components\Select::make('zone_ids')->label('Zona')->options(Zone::all()->pluck('name', 'id'))->multiple()->live(),
                        Forms\Components\CheckboxList::make('locality_ids')->label('Localidades')
                            ->options(fn (Get $get) => Locality::whereIn('zone_id', $get('zone_ids') ?? [])->pluck('name', 'id'))->columns(3)->required(),
                    ])
                    ->action(function (array $data) {
                        $count = Order::where('status', OrderStatus::Draft)->whereHas('client', fn($q) => $q->whereIn('locality_id', $data['locality_ids']))->update(['status' => OrderStatus::Processing]);
                        Notification::make()->title("Lanzamiento: {$count} pedidos enviados a armado.")->success()->send();
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