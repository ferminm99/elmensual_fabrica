<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OrderResource\Pages;
use App\Models\Order;
use App\Enums\Origin;
use App\Models\Client;
use App\Models\Sku;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;
    protected static ?string $navigationIcon = 'heroicon-o-shopping-cart';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Wizard::make([
                    // --- PASO 1: Cliente ---
                    Forms\Components\Wizard\Step::make('Cliente')
                        ->schema([
                            Forms\Components\Select::make('client_id')
                                ->label('Cliente')
                                ->options(Client::all()->pluck('name', 'id'))
                                ->searchable()
                                ->reactive() // Importante para detectar cambios
                                ->afterStateUpdated(function ($state, Forms\Set $set) {
                                    $client = Client::find($state);
                                    if ($client) {
                                        // Auto-selecciona Cta Cte si el cliente existe
                                        $set('payment_method', 'Current Account'); 
                                    }
                                })
                                ->required(),
                                
                            Forms\Components\DatePicker::make('created_at')
                                ->label('Fecha')
                                ->default(now()),
                        ]),

                    // --- PASO 2: Productos ---
                    Forms\Components\Wizard\Step::make('Items del Pedido')
                        ->schema([
                            Forms\Components\Repeater::make('items')
                                ->relationship()
                                ->live() // Para que recalcule el total al agregar items
                                ->schema([
                                    Forms\Components\Select::make('sku_id')
                                        ->label('Producto')
                                        ->options(Sku::with(['article', 'size', 'color'])->get()->mapWithKeys(function ($sku) {
                                            return [$sku->id => "{$sku->article->name} - {$sku->size->name} / {$sku->color->name}"];
                                        }))
                                        ->searchable()
                                        ->reactive()
                                        ->afterStateUpdated(function ($state, Forms\Set $set) {
                                            $sku = Sku::find($state);
                                            if ($sku) {
                                                $set('unit_price', $sku->article->base_cost * 1.5); 
                                                $set('stock_available', $sku->stock_quantity);
                                            }
                                        })
                                        ->required()
                                        ->columnSpan(2),

                                    Forms\Components\TextInput::make('stock_available')
                                        ->disabled()
                                        ->label('Stock')
                                        ->numeric(),

                                    Forms\Components\TextInput::make('quantity')
                                        ->numeric()
                                        ->default(1)
                                        ->required()
                                        ->live(), // Recalcula si cambia la cantidad

                                    Forms\Components\TextInput::make('unit_price')
                                        ->numeric()
                                        ->prefix('$')
                                        ->required()
                                        ->live(), // Recalcula si cambia el precio
                                ])
                                ->columns(5),
                        ]),

                    // --- PASO 3: Facturación y Totales ---
                    Forms\Components\Wizard\Step::make('Facturación y Cierre')
                        ->schema([
                            // --- RESUMEN DEL TOTAL (NUEVO) ---
                            Forms\Components\Section::make('Resumen')
                                ->schema([
                                    Forms\Components\Placeholder::make('grand_total')
                                        ->label('TOTAL A PAGAR')
                                        ->content(function (Forms\Get $get) {
                                            // Lógica para sumar todo en vivo
                                            $items = $get('items') ?? [];
                                            $total = 0;
                                            foreach ($items as $item) {
                                                $qty = intval($item['quantity'] ?? 0);
                                                $price = floatval($item['unit_price'] ?? 0);
                                                $total += $qty * $price;
                                            }
                                            return '$ ' . number_format($total, 2, ',', '.');
                                        })
                                        ->extraAttributes(['class' => 'text-3xl font-bold text-primary-600']),
                                ]),

                            Forms\Components\Grid::make(2)
                                ->schema([
                                    Forms\Components\Select::make('origin')
                                        ->label('Origen de la Venta')
                                        ->options([
                                            'Fiscal' => 'Fiscal (Blanco)',
                                            'Internal' => 'Interno (Negro)',
                                        ])
                                        ->default('Fiscal')
                                        ->required(),

                                    Forms\Components\Select::make('billing_strategy')
                                        ->label('Tipo de Comprobante')
                                        ->options([
                                            'Fiscal_A' => 'Factura A (AFIP)',
                                            'Fiscal_B' => 'Factura B (AFIP)',
                                            'Internal_X' => 'Remito X (Interno)',
                                        ])
                                        ->required(),

                                    Forms\Components\Select::make('status')
                                        ->label('Estado del Pedido')
                                        ->options(['Pending' => 'Pendiente', 'Completed' => 'Completado'])
                                        ->default('Pending')
                                        ->required(),
                                ]),
                        ]),
                ])
                ->columnSpan('full')
                ->skippable() // <--- ESTO PERMITE NAVEGAR LIBREMENTE
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->sortable(),
                Tables\Columns\TextColumn::make('client.name')->searchable(),
                Tables\Columns\TextColumn::make('total')->money('ARS'),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Completed' => 'success',
                        'Pending' => 'warning',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('origin')
                    ->badge()
                    ->color(fn (Origin $state): string => match ($state) {
                        Origin::FISCAL => 'success',
                        Origin::INTERNAL => 'danger',
                        default => 'gray',
                    }),
            ])
            ->filters([
                //
            ])
            // --- ACA AGREGAMOS EL BOTON DE IMPRIMIR ---
            ->actions([
                Tables\Actions\Action::make('print')
                    ->label('Imprimir')
                    ->icon('heroicon-o-printer')
                    ->url(fn (Order $record) => route('orders.pdf', $record))
                    ->openUrlInNewTab(), // Abre en otra pestaña
                    
                Tables\Actions\EditAction::make(), // Y mantenemos el botón de editar
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