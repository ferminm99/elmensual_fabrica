<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SupplierResource\Pages;
use App\Filament\Resources\ProductionOrderResource\RelationManagers\ActivitiesRelationManager;
use App\Filament\Resources\SupplierResource\RelationManagers\TransactionsRelationManager;
use App\Models\Supplier;
use Illuminate\Support\Facades\DB;
use App\Models\CompanyAccount;
use App\Models\Transaction;
use App\Models\Check;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;

// Función auxiliar para sumar los impuestos de la factura
function calcularTotalFactura(\Filament\Forms\Set $set, \Filament\Forms\Get $get) {
    $total = 
        (float) ($get('neto_gravado') ?: 0) +
        (float) ($get('no_gravado') ?: 0) +
        (float) ($get('exento') ?: 0) +
        (float) ($get('iva') ?: 0) +
        (float) ($get('perc_iva') ?: 0) +
        (float) ($get('perc_iibb') ?: 0) +
        (float) ($get('perc_imp_internos') ?: 0);
        
    $set('total', round($total, 2));
}

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
                        Forms\Components\TextInput::make('name')->label('Razón Social')->required(),
                        Forms\Components\TextInput::make('tax_id')->label('CUIT'),
                        Forms\Components\TextInput::make('email')->email(),
                        Forms\Components\TextInput::make('phone')->label('Teléfono'),
                        Forms\Components\TextInput::make('address')->label('Dirección')->columnSpanFull(),
                    ])->columns(2),

                Forms\Components\Section::make('Estado de Cuenta')
                    ->schema([
                        Forms\Components\TextInput::make('account_balance_fiscal')->label('Saldo a Pagar (Blanco)')->prefix('$')->numeric()->default(0),
                        Forms\Components\TextInput::make('account_balance_internal')->label('Saldo a Pagar (Negro)')->prefix('$')->numeric()->default(0),
                    ])->columns(2),

                // --- NUEVO REPEATER DE CUENTAS BANCARIAS CON AUTO-DETECCIÓN ---
                Forms\Components\Section::make('Cuentas Bancarias')
                    ->schema([
                        Forms\Components\Repeater::make('bankAccounts')
                            ->relationship() // Relación con SupplierBankAccount
                            ->schema([
                                Forms\Components\TextInput::make('cbu_cvu')
                                    ->label('CBU / CVU')
                                    ->length(22)
                                    ->required()
                                    ->live(debounce: 500) // Espera medio segundo que dejes de tipear
                                    ->afterStateUpdated(function (Set $set, $state) {
                                        // Si ya tipeó al menos 3 números...
                                        if (strlen($state) >= 3) {
                                            $prefix = substr($state, 0, 3);
                                            // Busca en la tabla 'banks' el banco con ese código
                                            $bank = \App\Models\Bank::where('code', $prefix)->first();
                                            
                                            // Si lo encuentra, autocompleta el select de abajo
                                            if ($bank) {
                                                $set('bank_id', $bank->id);
                                            } else {
                                                // Si no encuentra el código, asume que es billetera virtual (CVU)
                                                $billetera = \App\Models\Bank::where('code', '000')->first();
                                                if ($billetera) {
                                                    $set('bank_id', $billetera->id);
                                                }
                                            }
                                        }
                                    }),
                                    
                                Forms\Components\Select::make('bank_id')
                                    ->label('Banco')
                                    ->relationship('bank', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->required(),
                                    
                                Forms\Components\TextInput::make('alias')
                                    ->label('Alias'),
                            ])
                            ->columns(3)
                            // Para que la cajita cerrada muestre el nombre del banco
                            ->itemLabel(fn (array $state): ?string => \App\Models\Bank::find($state['bank_id'])?->name ?? 'Nueva Cuenta'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        $noSpinnersClass = '[appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none';

        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Proveedor')->searchable()->weight('bold'),
                Tables\Columns\TextColumn::make('account_balance_fiscal')->label('Deuda Fiscal')->money('ARS')->sortable()
                    ->color(fn ($state) => $state > 0 ? 'danger' : 'success'),
                Tables\Columns\TextColumn::make('account_balance_internal')->label('Deuda Interna')->money('ARS')->color('warning')->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('real_balance')
                    ->label('DEUDA TOTAL')->money('ARS')
                    ->state(fn (Supplier $record) => $record->account_balance_fiscal + $record->account_balance_internal)
                    ->weight('black')
                    ->color(fn ($state) => $state > 0 ? 'danger' : ($state < 0 ? 'success' : 'gray'))
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                
                Tables\Actions\Action::make('cargar_factura')
                    ->label('Cargar Factura')->icon('heroicon-o-document-plus')->color('warning')->modalWidth('6xl')
                    ->form([
                        Forms\Components\Section::make('Datos del Comprobante')->schema([
                            Forms\Components\TextInput::make('cae')->label('CAE / CAI (Opcional)')->numeric()->extraInputAttributes(['class' => $noSpinnersClass]),
                            Forms\Components\FileUpload::make('attachment')->label('Adjuntar PDF o Foto')->directory('proveedores/facturas')->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])->maxSize(5120)->columnSpanFull(),
                            Forms\Components\Grid::make(4)->schema([
                                Forms\Components\Select::make('tipo_comprobante')->label('Tipo (AFIP)')->options(['01' => 'Factura A (01)', '06' => 'Factura B (06)', '11' => 'Factura C (11)', '02' => 'Nota Débito A (02)', '03' => 'Nota Crédito A (03)', 'X' => 'Remito / Interno (Negro)'])->default('01')->required()->live(),
                                Forms\Components\TextInput::make('punto_vta')->label('Pto. Venta')->numeric()->required()->extraInputAttributes(['class' => $noSpinnersClass]),
                                Forms\Components\TextInput::make('numero')->label('Número')->numeric()->required()->extraInputAttributes(['class' => $noSpinnersClass]),
                                Forms\Components\DatePicker::make('fecha_emision')->label('Fecha')->default(now())->required(),
                            ]),
                        ]),

                        Forms\Components\Section::make('Desglose de Importes (Libro IVA)')->schema([
                            Forms\Components\Grid::make(4)->schema([
                                Forms\Components\TextInput::make('neto_gravado')->label('Neto Gravado')->numeric()->prefix('$')->default(0)->live(onBlur: true)->afterStateUpdated(fn (Set $set, Get $get) => calcularTotalFactura($set, $get))->extraInputAttributes(['class' => $noSpinnersClass]),
                                Forms\Components\TextInput::make('no_gravado')->label('No Gravado')->numeric()->prefix('$')->default(0)->live(onBlur: true)->afterStateUpdated(fn (Set $set, Get $get) => calcularTotalFactura($set, $get))->extraInputAttributes(['class' => $noSpinnersClass]),
                                Forms\Components\TextInput::make('exento')->label('Exento')->numeric()->prefix('$')->default(0)->live(onBlur: true)->afterStateUpdated(fn (Set $set, Get $get) => calcularTotalFactura($set, $get))->extraInputAttributes(['class' => $noSpinnersClass]),
                                Forms\Components\TextInput::make('iva')->label('I.V.A. Total')->numeric()->prefix('$')->default(0)->live(onBlur: true)->afterStateUpdated(fn (Set $set, Get $get) => calcularTotalFactura($set, $get))->extraInputAttributes(['class' => $noSpinnersClass]),
                                Forms\Components\TextInput::make('perc_iva')->label('Perc. IVA')->numeric()->prefix('$')->default(0)->live(onBlur: true)->afterStateUpdated(fn (Set $set, Get $get) => calcularTotalFactura($set, $get))->extraInputAttributes(['class' => $noSpinnersClass]),
                                Forms\Components\TextInput::make('perc_iibb')->label('Perc. IIBB')->numeric()->prefix('$')->default(0)->live(onBlur: true)->afterStateUpdated(fn (Set $set, Get $get) => calcularTotalFactura($set, $get))->extraInputAttributes(['class' => $noSpinnersClass]),
                                Forms\Components\TextInput::make('perc_imp_internos')->label('Imp. Internos')->numeric()->prefix('$')->default(0)->live(onBlur: true)->afterStateUpdated(fn (Set $set, Get $get) => calcularTotalFactura($set, $get))->extraInputAttributes(['class' => $noSpinnersClass]),

                                // TOTAL FINAL (Diseño mejorado)
                                Forms\Components\TextInput::make('total')
                                    ->label('TOTAL FINAL')->numeric()->prefix('$')->required()->readOnly()
                                    ->extraInputAttributes([
                                        'class' => "text-2xl font-black text-right !bg-white dark:!bg-gray-900 !text-primary-600 dark:!text-primary-400 !border-2 !border-primary-500 shadow-lg {$noSpinnersClass}"
                                    ]),
                            ]),
                        ]),
                    ])
                    ->action(function (array $data, Supplier $record) {
                        $isFiscal = $data['tipo_comprobante'] !== 'X';
                        $amount = (float) $data['total'];
                        $nroComprobante = str_pad($data['punto_vta'], 4, '0', STR_PAD_LEFT) . '-' . str_pad($data['numero'], 8, '0', STR_PAD_LEFT);

                        DB::transaction(function () use ($data, $record, $isFiscal, $amount, $nroComprobante) {
                            if ($isFiscal) {
                                $record->increment('account_balance_fiscal', $amount);
                                \App\Models\SupplierInvoice::create([
                                    'supplier_id' => $record->id,
                                    'tipo_comprobante' => $data['tipo_comprobante'],
                                    'numero' => $nroComprobante,
                                    'fecha_emision' => $data['fecha_emision'],
                                    'cae' => $data['cae'] ?? null,
                                    'attachment' => $data['attachment'] ?? null,
                                    'neto_gravado' => $data['neto_gravado'] ?? 0,
                                    'no_gravado' => $data['no_gravado'] ?? 0,
                                    'exento' => $data['exento'] ?? 0,
                                    'iva' => $data['iva'] ?? 0,
                                    'perc_iva' => $data['perc_iva'] ?? 0,
                                    'perc_iibb' => $data['perc_iibb'] ?? 0,
                                    'perc_imp_internos' => $data['perc_imp_internos'] ?? 0,
                                    'total' => $amount,
                                ]);
                            } else {
                                $record->increment('account_balance_internal', $amount);
                                Transaction::create([
                                    'supplier_id' => $record->id,
                                    'type' => 'Outcome', 
                                    'amount' => $amount,
                                    'description' => "Remito Compra: {$nroComprobante}",
                                    'concept' => 'Mercadería (Interno)',
                                    'origin' => 'Internal',
                                ]);
                            }
                        });
                        Notification::make()->success()->title('Comprobante guardado exitosamente')->send();
                    }),

                // --- BOTÓN PAGAR (SALIDA DE DINERO Y CHEQUES) ---
                Tables\Actions\Action::make('register_payment')
                    ->label('Pagar')
                    ->icon('heroicon-o-banknotes')
                    ->color('danger')
                    ->modalWidth('4xl')
                    ->form([
                        Forms\Components\Section::make('Estrategia de Pago')->schema([
                            Forms\Components\Grid::make(3)->schema([
                                Forms\Components\Select::make('split_strategy')
                                    ->label('Imputar a (Blanco/Negro):')
                                    ->options([
                                        'fiscal_100' => 'Deuda Blanca (Fiscal)',
                                        'internal_100' => 'Deuda Negra (Interna)',
                                        'split_50_50' => 'Mitad y Mitad',
                                    ])
                                    ->default('fiscal_100')
                                    ->live() 
                                    ->required(),
                                    
                                Forms\Components\Select::make('payment_method')
                                    ->label('Forma de Pago')
                                    ->options([
                                        'transferencia' => 'Transferencia / Efectivo',
                                        'cheque' => 'Pago con Cheques',
                                    ])
                                    ->default('transferencia')
                                    ->live()
                                    ->required(),

                                Forms\Components\TextInput::make('amount')
                                    ->label('Monto a Pagar')
                                    ->numeric()
                                    ->prefix('$')
                                    ->disabled(fn(Get $get) => $get('payment_method') === 'cheque' && $get('check_type') === 'tercero')
                                    ->dehydrated()
                                    ->required(),
                            ]),
                        ]),

                        Forms\Components\Section::make('Detalle del Pago Bancario')
                            ->visible(fn(Get $get) => $get('payment_method') === 'transferencia')
                            ->schema([
                                Forms\Components\Select::make('company_account_id')
                                    ->label('Cuenta Origen (Tuya)')
                                    ->options(\App\Models\CompanyAccount::all()->mapWithKeys(function ($acc) {
                                        return [$acc->id => "{$acc->name} (Saldo: $ " . number_format($acc->current_balance, 0, ',', '.') . ")"];
                                    }))
                                    ->required(fn(Get $get) => $get('payment_method') === 'transferencia'),
                                Forms\Components\Select::make('supplier_bank_account_id')
                                    ->label('Cuenta Destino (Del Proveedor)')
                                    ->options(fn (Supplier $record) => $record->bankAccounts->pluck('bank_name', 'id'))
                                    ->placeholder('Opcional: Seleccionar CBU guardado...'),
                            ]),

                        Forms\Components\Section::make('Gestión de Cheques')
                            ->visible(fn(Get $get) => $get('payment_method') === 'cheque')
                            ->schema([
                                Forms\Components\Radio::make('check_type')
                                    ->label('Origen del Cheque')
                                    ->options([
                                        'propio' => 'Emitir Cheque Propio',
                                        'tercero' => 'Usar Cheque de Terceros (En Cartera)',
                                    ])
                                    ->default('tercero')
                                    ->inline()
                                    ->live(),

                                // CARTERA DE TERCEROS
                                Forms\Components\CheckboxList::make('selected_checks')
                                    ->label('Seleccionar Cheques (Auto-sumará el monto)')
                                    ->visible(fn(Get $get) => $get('check_type') === 'tercero')
                                    ->options(function (Get $get) {
                                        $query = Check::where('status', 'InPortfolio');
                                        
                                        // AHORA FILTRAMOS POR 'origin' EN VEZ DE 'accounting_type'
                                        if ($get('split_strategy') === 'fiscal_100') $query->where('origin', 'Fiscal');
                                        if ($get('split_strategy') === 'internal_100') $query->where('origin', 'Internal');
                                        
                                        return $query->get()->mapWithKeys(function($check) {
                                            $tipo = $check->origin === 'Fiscal' ? 'Blanco' : 'Negro';
                                            $echeq = $check->is_echeq ? 'ECHEQ' : 'Físico';
                                            $cliente = $check->client->name ?? 'Desconocido';
                                            return [$check->id => "$ " . number_format($check->amount, 2) . " | $cliente | Nro: {$check->number} | ($tipo - $echeq)"];
                                        });
                                    })
                                    ->columns(1)
                                    ->live()
                                    ->afterStateUpdated(function (Get $get, Set $set, $state) {
                                        $sum = Check::whereIn('id', $state ?? [])->sum('amount');
                                        $set('amount', $sum);
                                    }),
                            ]),
                    ])
                    ->action(function (array $data, Supplier $record) {
                        $amount = (float) $data['amount'];
                        $strategy = $data['split_strategy'];

                        DB::transaction(function () use ($data, $record, $amount, $strategy) {
                            $fiscalPart = 0; $internalPart = 0;
                            if ($strategy === 'fiscal_100') $fiscalPart = $amount;
                            elseif ($strategy === 'internal_100') $internalPart = $amount;
                            else { $fiscalPart = $amount / 2; $internalPart = $amount / 2; }

                            if ($fiscalPart > 0) $record->decrement('account_balance_fiscal', $fiscalPart);
                            if ($internalPart > 0) $record->decrement('account_balance_internal', $internalPart);

                            if ($data['payment_method'] === 'cheque' && $data['check_type'] === 'tercero') {
                                if (!empty($data['selected_checks'])) {
                                    Check::whereIn('id', $data['selected_checks'])->update([
                                        // AQUÍ CORREGÍ "entregado" POR "Delivered"
                                        'status' => 'Delivered', 
                                        'supplier_id' => $record->id 
                                    ]);
                                }
                            }

                            if ($data['payment_method'] === 'transferencia') {
                                $account = \App\Models\CompanyAccount::find($data['company_account_id']);
                                if ($account) {
                                    $account->decrement('current_balance', $amount);
                                    \App\Models\Transaction::create([
                                        'company_account_id' => $account->id,
                                        'supplier_id' => $record->id,
                                        'type' => 'Expense',
                                        'amount' => $amount,
                                        'description' => "Pago a {$record->name}",
                                        'origin' => $strategy === 'internal_100' ? 'Internal' : 'Fiscal',
                                    ]);
                                }
                            }
                        });

                        Notification::make()->success()->title('Pago realizado correctamente.')->send();
                    }),
            ]);
    }
    
    public static function getPages(): array {
        return [
            'index' => Pages\ListSuppliers::route('/'),
            'create' => Pages\CreateSupplier::route('/create'),
            'edit' => Pages\EditSupplier::route('/{record}/edit'),
        ];
    }

    public static function getRelations(): array {
        return [
            ActivitiesRelationManager::class,
            TransactionsRelationManager::class,
        ];
    }
}