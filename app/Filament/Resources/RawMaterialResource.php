<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RawMaterialResource\Pages;
use App\Filament\Resources\RawMaterialResource\RelationManagers\SuppliersRelationManager;
use App\Models\RawMaterial;
use App\Models\Supplier;
use App\Models\CompanyAccount;
use App\Models\RawMaterialStock;
use App\Models\Transaction;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;

class RawMaterialResource extends Resource
{
    protected static ?string $model = RawMaterial::class;
    protected static ?string $navigationIcon = 'heroicon-o-cube';
    protected static ?string $modelLabel = 'Materia Prima';
    protected static ?string $pluralModelLabel = 'Materias Primas';
    protected static ?string $navigationGroup = 'Producción';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Datos del Insumo')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nombre del Insumo')
                            ->required(),
                        
                        Forms\Components\Grid::make(2)->schema([
                            Forms\Components\Select::make('unit')
                                ->label('Unidad de Medida')
                                ->options([
                                    'mts' => 'Metros',
                                    'kg' => 'Kilos',
                                    'unid' => 'Unidades',
                                ])
                                ->required(),
                            
                            Forms\Components\TextInput::make('cost_per_unit')
                                ->label('Costo Base Referencia')
                                ->numeric()
                                ->prefix('$'),
                        ]),
                    ]),

                Forms\Components\Section::make('Inventario por Color')
                    ->schema([
                        Forms\Components\Repeater::make('stocks')
                            ->relationship()
                            ->schema([
                                Forms\Components\Select::make('color_id')
                                    ->relationship('color', 'name')
                                    ->required(),
                                Forms\Components\TextInput::make('quantity')
                                    ->label('Cantidad')
                                    ->numeric()
                                    ->required(),
                                Forms\Components\TextInput::make('location')
                                    ->label('Ubicación')
                                    ->placeholder('Estante 1...'),
                            ])
                            ->columns(3)
                            ->addActionLabel('Agregar Color / Variante')
                    ])
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Insumo')
                    ->weight('bold')
                    ->searchable(),

                Tables\Columns\TextColumn::make('unit')
                    ->label('Unidad')
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('total_stock')
                    ->label('Stock Total')
                    ->state(fn (RawMaterial $record) => $record->stocks->sum('quantity'))
                    ->weight('bold')
                    ->color('success'),

                Tables\Columns\TextColumn::make('variants_summary')
                    ->label('Detalle Colores')
                    ->html()
                    ->state(function (RawMaterial $record) {
                        if ($record->stocks->isEmpty()) return '<span class="text-gray-400 text-xs">Sin stock</span>';
                        
                        $html = '<div class="flex flex-wrap gap-2">';
                        foreach ($record->stocks as $stock) {
                            $colorName = $stock->color->name ?? '?';
                            $hex = $stock->color->hex_code ?? '#cccccc';
                            $qty = floatval($stock->quantity);
                            
                            $html .= "
                                <div style='display: inline-flex; align-items: center; background-color: #f3f4f6; color: #374151; padding: 2px 8px; border-radius: 99px; font-size: 0.75rem; border: 1px solid #e5e7eb; gap: 6px;'>
                                    <span style='width: 10px; height: 10px; border-radius: 50%; background-color: {$hex}; border: 1px solid #9ca3af;'></span>
                                    <span>{$colorName}: <b>{$qty}</b></span>
                                </div>";
                        }
                        $html .= '</div>';
                        return $html;
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),

                // --- ACCIÓN DE COMPRA A PROVEEDOR ---
                Tables\Actions\Action::make('buy_stock')
                    ->label('Comprar Stock')
                    ->icon('heroicon-o-shopping-cart')
                    ->color('warning')
                    ->form([
                        // 1. SELECCIÓN DE PROVEEDOR (Con precios)
                        Forms\Components\Select::make('supplier_id')
                            ->label('Proveedor')
                            ->options(function (RawMaterial $record) {
                                // Mostramos nombre y precio pactado
                                return $record->suppliers()
                                    ->get()
                                    ->mapWithKeys(function ($s) {
                                        return [$s->id => "{$s->name} - Costo: $ " . number_format($s->pivot->price, 2)];
                                    });
                            })
                            ->required()
                            ->reactive()
                            ->afterStateUpdated(function ($state, callable $set, RawMaterial $record) {
                                $supplier = $record->suppliers()->find($state);
                                if ($supplier) $set('unit_cost', $supplier->pivot->price);
                            }),

                        // 2. DETALLES DE LA COMPRA
                        Forms\Components\Grid::make(2)->schema([
                            Forms\Components\TextInput::make('quantity')
                                ->label('Cantidad')
                                ->numeric()
                                ->required()
                                ->reactive()
                                ->afterStateUpdated(fn ($state, callable $set, callable $get) => $set('total_cost', $state * $get('unit_cost'))),

                            Forms\Components\TextInput::make('unit_cost')
                                ->label('Costo Unit.')
                                ->numeric()
                                ->prefix('$')
                                ->required()
                                ->reactive()
                                ->afterStateUpdated(fn ($state, callable $set, callable $get) => $set('total_cost', $state * $get('quantity'))),
                        ]),

                        Forms\Components\TextInput::make('total_cost')
                            ->label('Total a Pagar')
                            ->prefix('$')
                            ->disabled()
                            ->dehydrated(),

                        // 3. COLOR (Para saber a qué stock sumar)
                        Forms\Components\Select::make('color_id')
                            ->label('Color')
                            ->relationship('stocks.color', 'name') // Usa los colores ya existentes
                            ->required()
                            ->createOptionForm([ // Permitir crear color nuevo al vuelo si no existe
                                Forms\Components\TextInput::make('name')->required(),
                                Forms\Components\ColorPicker::make('hex_code'),
                            ]),

                        // 4. PAGO
                        Forms\Components\Select::make('payment_origin')
                            ->label('Forma de Pago')
                            ->options([
                                'debt' => 'Cuenta Corriente (Deuda)',
                                'cash' => 'Caja (Efectivo)',
                            ])
                            ->default('debt')
                            ->reactive()
                            ->required(),
                            
                        Forms\Components\Select::make('company_account_id')
                            ->label('Caja de Salida')
                            ->options(CompanyAccount::all()->pluck('name', 'id'))
                            ->visible(fn (Forms\Get $get) => $get('payment_origin') === 'cash')
                            ->required(fn (Forms\Get $get) => $get('payment_origin') === 'cash'),
                    ])
                    ->action(function (RawMaterial $record, array $data) {
                        $supplier = Supplier::find($data['supplier_id']);
                        $total = $data['total_cost'];

                        // 1. ACTUALIZAR STOCK (Sumar o crear)
                        $stock = RawMaterialStock::firstOrCreate(
                            ['raw_material_id' => $record->id, 'color_id' => $data['color_id']],
                            ['quantity' => 0, 'location' => 'Depósito']
                        );
                        $stock->increment('quantity', $data['quantity']);

                        // 2. REGISTRAR PAGO O DEUDA
                        if ($data['payment_origin'] === 'debt') {
                            $supplier->increment('internal_debt', $total);
                            Notification::make()->title('Compra registrada en Cta. Cte.')->success()->send();
                        } else {
                            $acc = CompanyAccount::find($data['company_account_id']);
                            $acc->decrement('current_balance', $total);
                            
                            Transaction::create([
                                'company_account_id' => $acc->id,
                                'supplier_id' => $supplier->id,
                                'type' => 'Expense',
                                'amount' => $total,
                                'description' => "Compra {$data['quantity']} {$record->unit} {$record->name}",
                                'concept' => 'Compra Insumos',
                                'origin' => 'Internal',
                            ]);
                            Notification::make()->title('Compra pagada con Caja')->success()->send();
                        }
                    }),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            // Aquí iría tu SuppliersRelationManager para cargar los precios
            SuppliersRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRawMaterials::route('/'),
            'create' => Pages\CreateRawMaterial::route('/create'),
            'edit' => Pages\EditRawMaterial::route('/{record}/edit'),
        ];
    }
}