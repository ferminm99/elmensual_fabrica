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

        // LÓGICA AUTO-ADD (Sin cambios, funciona bien)

        $autoAddRow = function (Get $get, Set $set) {

            $variants = $get('../../variants') ?? [];

            if (empty($variants)) return;

            $lastKey = array_key_last($variants);

            $lastItem = $variants[$lastKey];

            if (!empty($lastItem['color_id']) || !empty($lastItem['sku_id']) || ($lastItem['quantity'] ?? 0) > 0) {

                $variants[] = ['color_id' => null, 'sku_id' => null, 'quantity' => null, 'unit_price' => null];

                $set('../../variants', $variants);

            }

        };



        return $form

            ->schema([

                // --- CABECERA ---

                Forms\Components\Section::make()

                    ->compact()

                    ->schema([

                        Forms\Components\Grid::make(4)->schema([

                            Forms\Components\Select::make('client_id')->label('Cliente')->options(Client::all()->pluck('name', 'id'))->searchable()->required(),

                            Forms\Components\Select::make('billing_type')->label('Facturación')->options(['fiscal' => 'Fiscal', 'informal' => 'Interno', 'mixed' => 'Mixto'])->default('fiscal')->required(),

                            Forms\Components\Select::make('status')->label('Estado')->options(OrderStatus::class)->default(OrderStatus::Draft)->required(),

                            Forms\Components\DatePicker::make('order_date')->label('Fecha')->default(now())->required(),

                        ])

                    ]),



                // --- CARGA DE PRODUCTOS ---

                Forms\Components\Section::make('Carga de Artículos')

                    ->compact()

                    ->headerActions([

                        Forms\Components\Actions\Action::make('matrix_load')

                            ->label('GENERAR MÚLTIPLES')

                            ->color('primary')

                            ->icon('heroicon-o-squares-2x2')

                            ->form([

                                Forms\Components\Select::make('article_id')->label('Artículo Base')

                                    ->options(Article::all()->mapWithKeys(fn($a) => [$a->id => "{$a->code} - {$a->name}"]))

                                    ->searchable()->live()->required()

                                    ->afterStateUpdated(fn (Set $set) => $set('color_ids', []) && $set('size_ids', [])),

                                Forms\Components\TextInput::make('quantity_per_unit')->label('Cant. por variante')->numeric()->default(1)->required(),

                                Forms\Components\CheckboxList::make('color_ids')->label('Colores')

                                    ->options(fn (Get $get) => Sku::where('article_id', $get('article_id'))->with('color')->get()->unique('color_id')->pluck('color.name', 'color.id'))

                                    ->columns(4)->bulkToggleable(),

                                Forms\Components\CheckboxList::make('size_ids')->label('Talles')

                                    ->options(fn (Get $get) => Sku::where('article_id', $get('article_id'))->with('size')->get()->unique('size_id')->pluck('size.name', 'size.id'))

                                    ->columns(8)->gridDirection('row')->bulkToggleable(),

                            ])

                            ->action(function (array $data, Set $set, Get $get) {

                                $articleId = $data['article_id'];

                                $qty = intval($data['quantity_per_unit']);

                                $article = Article::find($articleId);

                                $newVariants = [];

                                foreach ($data['color_ids'] ?? [] as $colorId) {

                                    foreach ($data['size_ids'] ?? [] as $sizeId) {

                                        $sku = Sku::where('article_id', $articleId)->where('color_id', $colorId)->where('size_id', $sizeId)->first();

                                        if ($sku) $newVariants[] = ['color_id' => $colorId, 'sku_id' => $sku->id, 'quantity' => $qty, 'unit_price' => $article->base_cost];

                                    }

                                }

                                if (empty($newVariants)) return;

                                $currentGroups = $get('article_groups') ?? [];

                                $groupIndex = -1;

                                foreach ($currentGroups as $index => $group) { if ($group['article_id'] == $articleId) { $groupIndex = $index; break; } }

                                if ($groupIndex >= 0) {

                                    $currentGroups[$groupIndex]['variants'] = array_merge($currentGroups[$groupIndex]['variants'] ?? [], $newVariants);

                                } else {

                                    $currentGroups[] = ['article_id' => $articleId, 'variants' => $newVariants];

                                }

                                $set('article_groups', $currentGroups);

                                Notification::make()->title("Agregados")->success()->send();

                            }),

                    ])

                    ->schema([

                        Forms\Components\Repeater::make('article_groups')

                            ->hiddenLabel()

                            ->addActionLabel('Agregar otro Artículo')

                            ->defaultItems(0)

                            ->collapsible()

                            ->itemLabel(function (array $state): ?HtmlString {

                                $articleId = $state['article_id'] ?? null;

                                if (!$articleId) return new HtmlString("<span class='text-gray-400 italic'>Nuevo Artículo...</span>");

                                $article = Article::find($articleId);

                                if (!$article) return null;



                                $variants = $state['variants'] ?? [];

                                $qty = 0; $total = 0;

                                foreach($variants as $v) {

                                    $q = intval($v['quantity'] ?? 0);

                                    $qty += $q;

                                    $total += ($q * floatval($v['unit_price'] ?? 0));

                                }

                               

                                return new HtmlString("

                                    <div class='flex justify-between items-center w-full px-1'>

                                        <div class='flex items-center gap-2 overflow-hidden'>

                                            <span class='font-black text-primary-600 text-lg'>{$article->code}</span>

                                            <span class='font-medium text-gray-800 truncate'>{$article->name}</span>

                                        </div>

                                        <div class='flex items-center gap-2 text-sm whitespace-nowrap ml-2'>

                                            <span class='bg-gray-100 text-gray-700 px-2 py-0.5 rounded font-bold'>{$qty} u.</span>

                                            <span class='bg-green-50 text-green-700 px-2 py-0.5 rounded font-bold'>$ " . number_format($total, 0, ',', '.') . "</span>

                                        </div>

                                    </div>

                                ");

                            })

                            ->schema([

                                // SELECCIÓN DE ARTÍCULO

                                Forms\Components\Select::make('article_id')

                                    ->hiddenLabel()->placeholder('Buscar Artículo...')

                                    ->options(Article::all()->mapWithKeys(fn($a) => [$a->id => "{$a->code} - {$a->name}"]))

                                    ->searchable()->required()->live()

                                    ->afterStateUpdated(fn (Set $set) => $set('variants', [['color_id' => null, 'sku_id' => null, 'quantity' => null]]))

                                    ->columnSpanFull(),



                                // --- CABECERA DE TABLA VISUAL (GRID 12) ---

                                Forms\Components\Grid::make(12)

                                    ->extraAttributes(['class' => 'mb-1 border-b border-gray-200 pb-1'])

                                    ->schema([

                                        Forms\Components\Placeholder::make('h1')->content('COLOR')->hiddenLabel()->extraAttributes(['class' => 'text-xs font-bold text-gray-500'])->columnSpan(3),

                                        Forms\Components\Placeholder::make('h2')->content('TALLE')->hiddenLabel()->extraAttributes(['class' => 'text-xs font-bold text-gray-500'])->columnSpan(3),

                                        Forms\Components\Placeholder::make('h3')->content('CANT.')->hiddenLabel()->extraAttributes(['class' => 'text-xs font-bold text-gray-500 text-center'])->columnSpan(2),

                                        Forms\Components\Placeholder::make('h4')->content('PRECIO')->hiddenLabel()->extraAttributes(['class' => 'text-xs font-bold text-gray-500'])->columnSpan(3),

                                        Forms\Components\Placeholder::make('h5')->content('')->hiddenLabel()->columnSpan(1),

                                    ]),


                                // --- REPEATER DE FILAS ---
                                // --- REPEATER DE FILAS ---
                                // --- REPEATER DE FILAS ---
// --- REPEATER DE FILAS ---
Forms\Components\Repeater::make('variants')
    ->hiddenLabel()
    ->addable(false)
    ->deletable(false)
    ->reorderable(false)
    ->collapsible(false)
    ->defaultItems(1)
    ->extraAttributes(['class' => 'tabla-ultra-compacta']) // Clase para el CSS
    ->schema([
        // 1. INYECTOR DE CSS REAL: Esto elimina el espacio que Filament pone "por fuera" de tus inputs
        Forms\Components\Placeholder::make('css_limpiador')
            ->content(new \Illuminate\Support\HtmlString('
                <style>
                    /* Eliminamos el aire entre las filas del repeater */
                    .tabla-ultra-compacta .fi-fo-repeater-item { 
                        padding: 0 !important; 
                        margin: 0 !important; 
                        border: none !important; 
                        background: transparent !important; 
                        box-shadow: none !important; 
                    }
                    /* Eliminamos el espacio interno del contenido de la fila */
                    .tabla-ultra-compacta .fi-fo-repeater-item-content { 
                        padding: 0 !important; 
                        margin: 0 !important; 
                    }
                    /* Pegamos los bordes de la grilla */
                    .tabla-ultra-compacta .grid { 
                        gap: 4px !important; 
                    }
                </style>
            '))
            ->columnSpanFull()
            ->hiddenLabel(),

        Forms\Components\Grid::make(12)
            ->extraAttributes(['class' => 'items-center !gap-x-1 !gap-y-0 !m-0 !p-0']) 
            ->schema([
                
                // COLOR (CON CÍRCULO Y HEX)
                Forms\Components\Select::make('color_id')
                    ->hiddenLabel()
                    ->allowHtml()
                    ->searchable()
                    ->options(function (Get $get) {
                        $articleId = $get('../../article_id');
                        if (!$articleId) return [];
                        return \App\Models\Sku::where('article_id', $articleId)
                            ->join('colors', 'skus.color_id', '=', 'colors.id')
                            ->select('colors.id', 'colors.name', 'colors.hex_code')->distinct()->get()
                            ->mapWithKeys(fn ($c) => [
                                $c->id => "
                                    <div class='flex items-center gap-2'>
                                        <div class='w-3 h-3 rounded-full border border-gray-400' style='background-color:{$c->hex_code}'></div>
                                        <span class='text-xs'>{$c->name}</span>
                                    </div>
                                "
                            ]);
                    })
                    ->live()->afterStateUpdated($autoAddRow)
                    ->columnSpan(3)
                    ->extraInputAttributes(['class' => '!h-7 !py-0 text-xs']), // h-7 es el mínimo

                // TALLE (CON STOCK DISPONIBLE)
                Forms\Components\Select::make('sku_id')
                    ->hiddenLabel()
                    ->searchable()
                    ->options(function (Get $get) {
                        $articleId = $get('../../article_id'); $colorId = $get('color_id');
                        if (!$articleId || !$colorId) return [];
                        return \App\Models\Sku::where('article_id', $articleId)->where('color_id', $colorId)->with('size')->get()
                            ->mapWithKeys(fn($s) => [$s->id => $s->size->name . ' (Stock: '.$s->stock_quantity.')']);
                    })
                    ->live()->afterStateUpdated(function ($state, Set $set, Get $get) use ($autoAddRow) {
                        $sku = \App\Models\Sku::find($state);
                        if($sku) $set('unit_price', $sku->article->base_cost);
                        $autoAddRow($get, $set);
                    })
                    ->columnSpan(3)
                    ->extraInputAttributes(['class' => '!h-7 !py-0 text-xs']),

                // CANTIDAD (TEXTO VISIBLE)
                Forms\Components\TextInput::make('quantity')
                    ->hiddenLabel()
                    ->numeric()
                    ->live()->afterStateUpdated($autoAddRow)
                    ->columnSpan(2)
                    ->extraInputAttributes(['class' => '!h-7 !py-0 text-center font-bold text-sm']),

                // PRECIO
                Forms\Components\TextInput::make('unit_price')
                    ->hiddenLabel()
                    ->numeric()
                    ->disabled()
                    ->dehydrated()
                    ->columnSpan(3)
                    ->extraInputAttributes(['class' => '!h-7 !py-0 bg-gray-50/50 text-xs']),

                // BORRAR
                Forms\Components\Actions::make([
                    Forms\Components\Actions\Action::make('delete')
                        ->icon('heroicon-m-trash')
                        ->color('danger')
                        ->iconButton()
                        ->size(\Filament\Support\Enums\ActionSize::Small)
                        ->action(fn ($component) => $component->getContainer()->getParentComponent()->deleteItem($component->getContainer()->getKey()))
                ])
                ->columnSpan(1)
                ->extraAttributes(['class' => 'flex justify-center items-center h-7']),
            ])
    ])
                                ])

                            ->columns(1)

                    ]),

            ]);

    }



    public static function table(Table $table): Table

    {

        return $table

            ->columns([

                Tables\Columns\TextColumn::make('id')->label('#')->sortable(),

                Tables\Columns\TextColumn::make('client.name')->label('Cliente')->weight('bold')->searchable(),

                Tables\Columns\TextColumn::make('order_date')->date('d/m/Y')->label('Fecha'),

                Tables\Columns\SelectColumn::make('status')->options(OrderStatus::class)->label('Estado'),

                Tables\Columns\TextColumn::make('total_amount')->label('Total')->money('ARS')->weight('bold'),

            ])

            ->defaultSort('created_at', 'desc')

            ->actions([

                Tables\Actions\EditAction::make(),

                Tables\Actions\Action::make('facturar')

                    ->label('Cerrar Pedido')

                    ->icon('heroicon-o-check')

                    ->color('success')

                    ->visible(fn (Order $record) => $record->status !== OrderStatus::Delivered)

                    ->requiresConfirmation()

                    ->action(function (Order $record) {

                        $total = 0;

                        $record->items->each(function($item) use (&$total) {

                            if ($item->quantity > 0) {

                                $item->subtotal = $item->quantity * $item->unit_price;

                                $item->save();

                                $total += $item->subtotal;

                            } else {

                                $item->delete();

                            }

                        });

                        $record->update(['total_amount' => $total, 'status' => OrderStatus::Delivered]);

                       

                        $client = $record->client;

                        if ($record->billing_type === 'fiscal') {

                            $client->increment('fiscal_debt', $total);

                        } else {

                            $client->increment('internal_debt', $total);

                        }

                        Notification::make()->title('Cerrado')->body('$'.number_format($total,0))->success()->send();

                    }),

            ]);

    }



    public static function getRelations(): array { return []; }

    public static function getPages(): array

    {

        return [

            'index' => Pages\ListOrders::route('/'),

            'create' => Pages\CreateOrder::route('/create'),

            'edit' => Pages\EditOrder::route('/{record}/edit'),

        ];

    }

}