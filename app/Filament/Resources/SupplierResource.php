<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SupplierResource\Pages;
use App\Filament\Resources\ProductionOrderResource\RelationManagers\ActivitiesRelationManager;
use App\Filament\Resources\SupplierResource\RelationManagers\TransactionsRelationManager;
use App\Models\Supplier;
use Illuminate\Support\Facades\DB;
use App\Models\CompanyAccount;
use App\Models\Transaction;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;

class SupplierResource extends Resource
{
    protected static ?string $model = Supplier::class;

    protected static ?string $navigationIcon = 'heroicon-o-truck';
    
    protected static ?string $modelLabel = 'Proveedor';
    protected static ?string $pluralModelLabel = 'Proveedores';
    protected static ?string $navigationGroup = 'Producción';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Datos del Proveedor')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Razón Social')
                            ->required(),
                        Forms\Components\TextInput::make('tax_id')
                            ->label('CUIT'),
                        Forms\Components\TextInput::make('email')
                            ->email(),
                        Forms\Components\TextInput::make('phone')
                            ->label('Teléfono'),
                        Forms\Components\TextInput::make('address')
                            ->label('Dirección')
                            ->columnSpanFull(),
                    ])->columns(2),

                Forms\Components\Section::make('Estado de Cuenta')
                    ->schema([
                        Forms\Components\TextInput::make('account_balance_fiscal')
                            ->label('Saldo a Pagar (Blanco)')
                            ->prefix('$')
                            ->numeric()
                            ->default(0),
                        Forms\Components\TextInput::make('account_balance_internal')
                            ->label('Saldo a Pagar (Negro)')
                            ->prefix('$')
                            ->numeric()
                            ->default(0),
                    ])->columns(2),

                Forms\Components\Section::make('Datos Bancarios (Para Pagos)')
                    ->schema([
                        Forms\Components\TextInput::make('bank_name')->label('Banco'),
                        Forms\Components\TextInput::make('cbu')->label('CBU / CVU'),
                        Forms\Components\TextInput::make('alias')->label('Alias'),
                        Forms\Components\TextInput::make('account_number')->label('N° Cuenta'),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Proveedor')
                    ->searchable()
                    ->weight('bold'),

                // DEUDA FISCAL (Lo que debemos en blanco)
                Tables\Columns\TextColumn::make('account_balance_fiscal')
                    ->label('Deuda Fiscal')
                    ->money('ARS')
                    ->sortable()
                    ->color(fn ($state) => $state > 0 ? 'danger' : 'success'),

                // DEUDA INTERNA (Lo que debemos en negro) - Ocultable
                Tables\Columns\TextColumn::make('account_balance_internal')
                    ->label('Deuda Interna')
                    ->money('ARS')
                    ->color('warning')
                    ->toggleable(isToggledHiddenByDefault: true),

                // TOTAL REAL
                Tables\Columns\TextColumn::make('real_balance')
                    ->label('DEUDA TOTAL')
                    ->money('ARS')
                    ->state(fn (Supplier $record) => $record->account_balance_fiscal + $record->account_balance_internal)
                    ->weight('black')
                    // Le enseñamos: Si > 0 (Debemos) es Rojo. Si < 0 (Saldo a favor) es Verde. Si es 0 es Gris.
                    ->color(fn ($state) => $state > 0 ? 'danger' : ($state < 0 ? 'success' : 'gray'))
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                // --- BOTÓN 1: CARGAR FACTURA DE COMPRA (INGRESAR DEUDA) ---
                Tables\Actions\Action::make('cargar_factura')
                    ->label('Cargar Factura')
                    ->icon('heroicon-o-document-plus')
                    ->color('warning')
                    ->modalWidth('4xl')
                    ->form([
                        Forms\Components\Section::make('Datos del Comprobante')->schema([
                            Forms\Components\Grid::make(3)->schema([
                                Forms\Components\Select::make('tipo_comprobante')
                                    ->label('Tipo')
                                    ->options([
                                        'Factura A' => 'Factura A',
                                        'Factura B' => 'Factura B',
                                        'Factura C' => 'Factura C',
                                        'Ticket' => 'Remito / Interno (Negro)',
                                    ])
                                    ->default('Factura A')
                                    ->required()
                                    ->live(),

                                Forms\Components\TextInput::make('numero')
                                    ->label('Pto Vta - Número')
                                    ->placeholder('Ej: 0001-00001234')
                                    ->required(),

                                Forms\Components\DatePicker::make('fecha_emision')
                                    ->label('Fecha de Emisión')
                                    ->default(now())
                                    ->required(),
                            ]),
                        ]),

                        Forms\Components\Section::make('Importes')->schema([
                            Forms\Components\Grid::make(3)->schema([
                                Forms\Components\TextInput::make('neto_gravado')
                                    ->label('Neto Gravado')
                                    ->numeric()
                                    ->prefix('$')
                                    ->default(0)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function (Get $get, Set $set, $state) {
                                        if ($get('tipo_comprobante') === 'Factura A') {
                                            $set('iva', round((float)$state * 0.21, 2));
                                            $set('total', round((float)$state * 1.21, 2));
                                        } else {
                                            $set('iva', 0);
                                            $set('total', (float)$state);
                                        }
                                    }),

                                Forms\Components\TextInput::make('iva')
                                    ->label('I.V.A. (21%)')
                                    ->numeric()
                                    ->prefix('$')
                                    ->default(0)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function (Get $get, Set $set, $state) {
                                         $neto = (float)$get('neto_gravado');
                                         $set('total', $neto + (float)$state);
                                    })
                                    ->visible(fn (Get $get) => in_array($get('tipo_comprobante'), ['Factura A'])),

                                Forms\Components\TextInput::make('total')
                                    ->label('TOTAL FINAL')
                                    ->numeric()
                                    ->prefix('$')
                                    ->required()
                                    ->helperText('Importe total del comprobante.'),
                            ]),
                        ]),
                    ])
                    ->action(function (array $data, Supplier $record) {
                        $isFiscal = in_array($data['tipo_comprobante'], ['Factura A', 'Factura B', 'Factura C']);
                        $amount = (float) $data['total'];

                        DB::transaction(function () use ($data, $record, $isFiscal, $amount) {
                            // 1. Aumentamos la deuda con el proveedor
                            if ($isFiscal) {
                                $record->increment('account_balance_fiscal', $amount);
                            } else {
                                $record->increment('account_balance_internal', $amount);
                            }

                            // 2. Registramos el "gasto" para auditoría (Sin tocar bancos todavía)
                            Transaction::create([
                                'supplier_id' => $record->id, // Asumiendo que agregaste supplier_id a Transactions
                                'type' => 'Outcome', // Podrías usar 'Expense' si no querés crear un Enum nuevo
                                'amount' => $amount,
                                'description' => "Compra: {$data['tipo_comprobante']} {$data['numero']}",
                                'concept' => 'Mercadería / Insumos',
                                'origin' => $isFiscal ? 'Fiscal' : 'Internal',
                            ]);
                        });

                        Notification::make()->success()->title('Factura cargada. Saldo actualizado.')->send();
                    }),

                // --- BOTÓN 2: PAGAR (SALIDA DE DINERO REAL) ---
                Tables\Actions\Action::make('register_payment')
                    ->label('Pagar')
                    ->icon('heroicon-o-banknotes')
                    ->color('danger')
                    ->modalWidth('3xl')
                    ->form([
                        Forms\Components\Section::make('Detalle del Pago')->schema([
                            Forms\Components\Grid::make(3)->schema([
                                Forms\Components\TextInput::make('amount')
                                    ->label('Monto a Pagar')
                                    ->numeric()
                                    ->prefix('$')
                                    ->required()
                                    ->columnSpan(2),
                                
                                Forms\Components\Select::make('split_strategy')
                                    ->label('Imputar a:')
                                    ->options([
                                        'fiscal_100' => 'Deuda Blanca (Fiscal)',
                                        'internal_100' => 'Deuda Negra (Interna)',
                                        'split_50_50' => 'Mitad y Mitad',
                                    ])
                                    ->default('fiscal_100')
                                    ->required(),
                            ]),
                        ]),

                        Forms\Components\Section::make('Egreso de Tesorería')->schema([
                            Forms\Components\Grid::make(2)->schema([
                                Forms\Components\Select::make('company_account_id')
                                    ->label('Cuenta Origen')
                                    ->options(CompanyAccount::all()->mapWithKeys(function ($acc) {
                                        return [$acc->id => "{$acc->name} (Saldo: $ " . number_format($acc->current_balance, 0, ',', '.') . ")"];
                                    }))
                                    ->required(),

                                Forms\Components\Select::make('payment_method')
                                    ->label('Forma de Pago')
                                    ->options([
                                        'transferencia' => 'Transferencia',
                                        'efectivo' => 'Efectivo',
                                        'cheque' => 'Cheque',
                                    ])
                                    ->default('transferencia')
                                    ->required(),
                            ]),
                            Forms\Components\Textarea::make('notes')
                                ->label('Observaciones / Comprobante de pago')
                                ->placeholder('Ej: Transferencia Banco Provincia Nro 99123'),
                        ]),
                    ])
                    ->action(function (array $data, Supplier $record) {
                        $amount = (float) $data['amount'];
                        $strategy = $data['split_strategy'];

                        DB::transaction(function () use ($data, $record, $amount, $strategy) {
                            // 1. Bajamos la deuda
                            $fiscalPart = 0; $internalPart = 0;
                            if ($strategy === 'fiscal_100') $fiscalPart = $amount;
                            elseif ($strategy === 'internal_100') $internalPart = $amount;
                            else { $fiscalPart = $amount / 2; $internalPart = $amount / 2; }

                            if ($fiscalPart > 0) $record->decrement('account_balance_fiscal', $fiscalPart);
                            if ($internalPart > 0) $record->decrement('account_balance_internal', $internalPart);

                            // 2. Sacamos plata del Banco/Caja
                            $account = CompanyAccount::findOrFail($data['company_account_id']);
                            $account->decrement('current_balance', $amount);

                            // 3. Registramos el movimiento en el Banco
                            Transaction::create([
                                'company_account_id' => $account->id,
                                'supplier_id' => $record->id,
                                'type' => 'Outcome', // Egreso de dinero
                                'amount' => $amount,
                                'description' => "Pago a Proveedor: {$record->name}",
                                'concept' => 'Pago Proveedores',
                                'origin' => $strategy === 'internal_100' ? 'Internal' : 'Fiscal',
                                'payment_details' => ['method' => $data['payment_method'], 'notes' => $data['notes']],
                            ]);
                        });

                        Notification::make()->success()->title('Pago registrado')->body('El saldo del banco y la deuda se actualizaron.')->send();
                    }),
            ]);
    }
    
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSuppliers::route('/'),
            'create' => Pages\CreateSupplier::route('/create'),
            'edit' => Pages\EditSupplier::route('/{record}/edit'),
        ];
    }

    public static function getRelations(): array
    {
        return [
            ActivitiesRelationManager::class,
            TransactionsRelationManager::class,
        ];
    }
}