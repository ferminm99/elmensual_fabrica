<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SupplierResource\Pages;
use App\Filament\Resources\ProductionOrderResource\RelationManagers\ActivitiesRelationManager;
use App\Filament\Resources\SupplierResource\RelationManagers\TransactionsRelationManager;
use App\Filament\Resources\SupplierResource\RelationManagers\SupplierInvoicesRelationManager;
use App\Models\Supplier;
use Illuminate\Support\Facades\DB;
use App\Models\CompanyAccount;
use App\Models\Transaction;
use App\Models\Check;
use App\Models\Bank;
use App\Models\SupplierBankAccount;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;

// Función auxiliar para sumar los impuestos y sincronizar con el Pago al Contado
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

    // Si elegimos Contado, le pasamos el monto exacto al pago (salvo que sean cheques de 3ros que suman solos)
    if ($get('condicion_venta') === 'contado') {
        if (!($get('payment_method') === 'check' && $get('check_type') === 'tercero')) {
            $set('amount_paid', round($total, 2));
        }
    }
}

class SupplierResource extends Resource
{
    protected static ?string $model = Supplier::class;
    protected static ?string $navigationIcon = 'heroicon-o-truck';
    protected static ?string $modelLabel = 'Proveedor';
    protected static ?string $pluralModelLabel = 'Proveedores';
    protected static ?string $navigationGroup = 'Ventas';

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

                Forms\Components\Section::make('Cuentas Bancarias')
                    ->schema([
                        Forms\Components\Repeater::make('bankAccounts')
                            ->relationship() 
                            ->schema([
                                Forms\Components\TextInput::make('cbu_cvu')
                                    ->label('CBU / CVU')
                                    ->length(22)
                                    ->required()
                                    ->live(debounce: 500) 
                                    ->afterStateUpdated(function (Set $set, $state) {
                                        if (strlen($state) >= 3) {
                                            $prefix = substr($state, 0, 3);
                                            $bank = \App\Models\Bank::where('code', $prefix)->first();
                                            if ($bank) { $set('bank_id', $bank->id); } 
                                            else {
                                                $billetera = \App\Models\Bank::where('code', '000')->first();
                                                if ($billetera) { $set('bank_id', $billetera->id); }
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
                
                // --- BOTÓN CARGAR FACTURA (AHORA INCLUYE PAGO AL CONTADO) ---
                Tables\Actions\Action::make('cargar_factura')
                    ->label('Cargar Factura')
                    ->icon('heroicon-o-document-plus')
                    ->color('warning')
                    ->modalWidth('6xl')
                    ->closeModalByClickingAway(false) // <--- MAGIA: No se cierra al hacer clic afuera
                    ->form([
                        Forms\Components\Section::make('Datos del Comprobante')->schema([
                            Forms\Components\TextInput::make('cae')->label('CAE / CAI (Opcional)')->numeric()->extraInputAttributes(['class' => $noSpinnersClass]),
                            Forms\Components\FileUpload::make('attachment')->label('Adjuntar PDF o Foto')->directory('proveedores/facturas')->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])->maxSize(5120)->columnSpanFull(),
                            Forms\Components\Grid::make(4)->schema([
                                Forms\Components\Select::make('tipo_comprobante')->label('Tipo (AFIP)')->options(['01' => 'Factura A (01)', '06' => 'Factura B (06)', '11' => 'Factura C (11)', '02' => 'Nota Débito A (02)', '03' => 'Nota Crédito A (03)', 'X' => 'Remito / Interno (Negro)'])->default('01')->required()->live()->afterStateUpdated(fn (Set $set, Get $get) => calcularTotalFactura($set, $get)),
                                Forms\Components\TextInput::make('punto_vta')->label('Pto. Venta')->numeric()->required()->extraInputAttributes(['class' => $noSpinnersClass]),
                                Forms\Components\TextInput::make('numero')->label('Número')->numeric()->required()->extraInputAttributes(['class' => $noSpinnersClass]),
                                Forms\Components\DatePicker::make('fecha_emision')->label('Fecha')->default(now())->required(),
                            ]),
                        ]),

                        Forms\Components\Section::make('Desglose de Importes (Libro IVA)')->schema([
                            Forms\Components\Grid::make(4)->schema([
                                Forms\Components\TextInput::make('neto_gravado')->label('Neto Gravado')->numeric()->prefix('$')->default(0)->live(debounce: 500)->afterStateUpdated(fn (Set $set, Get $get) => calcularTotalFactura($set, $get))->extraInputAttributes(['class' => $noSpinnersClass]),
                                Forms\Components\TextInput::make('no_gravado')->label('No Gravado')->numeric()->prefix('$')->default(0)->live(debounce: 500)->afterStateUpdated(fn (Set $set, Get $get) => calcularTotalFactura($set, $get))->extraInputAttributes(['class' => $noSpinnersClass]),
                                Forms\Components\TextInput::make('exento')->label('Exento')->numeric()->prefix('$')->default(0)->live(debounce: 500)->afterStateUpdated(fn (Set $set, Get $get) => calcularTotalFactura($set, $get))->extraInputAttributes(['class' => $noSpinnersClass]),
                                Forms\Components\TextInput::make('iva')->label('I.V.A. Total')->numeric()->prefix('$')->default(0)->live(debounce: 500)->afterStateUpdated(fn (Set $set, Get $get) => calcularTotalFactura($set, $get))->extraInputAttributes(['class' => $noSpinnersClass]),
                                Forms\Components\TextInput::make('perc_iva')->label('Perc. IVA')->numeric()->prefix('$')->default(0)->live(debounce: 500)->afterStateUpdated(fn (Set $set, Get $get) => calcularTotalFactura($set, $get))->extraInputAttributes(['class' => $noSpinnersClass]),
                                Forms\Components\TextInput::make('perc_iibb')->label('Perc. IIBB')->numeric()->prefix('$')->default(0)->live(debounce: 500)->afterStateUpdated(fn (Set $set, Get $get) => calcularTotalFactura($set, $get))->extraInputAttributes(['class' => $noSpinnersClass]),
                                Forms\Components\TextInput::make('perc_imp_internos')->label('Imp. Internos')->numeric()->prefix('$')->default(0)->live(debounce: 500)->afterStateUpdated(fn (Set $set, Get $get) => calcularTotalFactura($set, $get))->extraInputAttributes(['class' => $noSpinnersClass]),

                                Forms\Components\TextInput::make('total')
                                    ->label('TOTAL FACTURA')->numeric()->prefix('$')->required()->readOnly()
                                    ->extraInputAttributes([
                                        'class' => "text-2xl font-black text-right !bg-white dark:!bg-gray-900 !text-primary-600 dark:!text-primary-400 !border-2 !border-primary-500 shadow-lg {$noSpinnersClass}"
                                    ]),
                            ]),
                        ]),

                        // --- SECCIÓN: CONDICIÓN DE VENTA Y PAGO INTEGRADO ---
                        Forms\Components\Section::make('Condición de Pago')
                            ->schema([
                                Forms\Components\Grid::make(3)->schema([
                                    Forms\Components\Select::make('condicion_venta')
                                        ->label('Condición')
                                        ->options([
                                            'cta_cte' => 'Cuenta Corriente (Cargar Deuda)',
                                            'contado' => 'Contado (Pagar Inmediatamente)',
                                        ])
                                        ->default('cta_cte')
                                        ->live()
                                        ->afterStateUpdated(fn (Set $set, Get $get) => calcularTotalFactura($set, $get))
                                        ->required(),

                                    Forms\Components\Select::make('payment_method')
                                        ->label('Forma de Pago')
                                        ->options([
                                            'cash' => 'Efectivo (Caja)',
                                            'transfer' => 'Transferencia Bancaria',
                                            'check' => 'Pago con Cheques',
                                        ])
                                        ->default('transfer')
                                        ->visible(fn(Get $get) => $get('condicion_venta') === 'contado')
                                        ->live()
                                        ->afterStateUpdated(fn (Set $set, Get $get) => calcularTotalFactura($set, $get))
                                        ->required(fn(Get $get) => $get('condicion_venta') === 'contado'),

                                    Forms\Components\TextInput::make('amount_paid')
                                        ->label('Monto a Pagar')
                                        ->numeric()
                                        ->prefix('$')
                                        ->visible(fn(Get $get) => $get('condicion_venta') === 'contado')
                                        ->disabled(fn(Get $get) => $get('payment_method') === 'check' && $get('check_type') === 'tercero')
                                        ->dehydrated()
                                        ->required(fn(Get $get) => $get('condicion_venta') === 'contado'),
                                ]),

                                // EFECTIVO
                                Forms\Components\Group::make()->schema([
                                    Forms\Components\Select::make('company_account_id_cash')
                                        ->label('¿De qué Caja sale el efectivo?')
                                        ->options(\App\Models\CompanyAccount::where('type', 'cash')->pluck('name', 'id'))
                                        ->required(fn(Get $get) => $get('payment_method') === 'cash' && $get('condicion_venta') === 'contado'),
                                ])->visible(fn(Get $get) => $get('condicion_venta') === 'contado' && $get('payment_method') === 'cash'),

                                // TRANSFERENCIA
                                Forms\Components\Group::make()->schema([
                                    Forms\Components\Select::make('company_account_id_bank')
                                        ->label('Cuenta Origen (Tuya)')
                                        ->options(\App\Models\CompanyAccount::where('type', 'bank')->pluck('name', 'id'))
                                        ->required(fn(Get $get) => $get('payment_method') === 'transfer' && $get('condicion_venta') === 'contado'),
                                    
                                    Forms\Components\Radio::make('destination_type')
                                        ->label('Cuenta Destino (Proveedor)')
                                        ->options(['saved' => 'Elegir cuenta guardada', 'new' => 'Cargar un CBU/CVU nuevo ahora'])
                                        ->default('saved')->inline()->live(),

                                    Forms\Components\Select::make('supplier_bank_account_id')
                                        ->label('Seleccionar Cuenta del Proveedor')
                                        ->options(fn ($record) => $record->bankAccounts()->with('bank')->get()->mapWithKeys(fn($acc) => [$acc->id => "{$acc->bank->name} - {$acc->cbu_cvu}"]))
                                        ->visible(fn(Get $get) => $get('destination_type') === 'saved')
                                        ->required(fn(Get $get, $record) => $get('destination_type') === 'saved' && $record->bankAccounts->count() > 0),

                                    Forms\Components\Grid::make(2)->visible(fn(Get $get) => $get('destination_type') === 'new')->schema([
                                        Forms\Components\TextInput::make('new_cbu_cvu')
                                            ->label('Nuevo CBU / CVU')->length(22)->live(debounce: 500)
                                            ->afterStateUpdated(function (Set $set, $state) {
                                                if (strlen($state) >= 3) {
                                                    $prefix = substr($state, 0, 3);
                                                    $bank = \App\Models\Bank::where('code', $prefix)->first();
                                                    if ($bank) { $set('new_bank_id', $bank->id); } 
                                                    else {
                                                        $billetera = \App\Models\Bank::where('code', '000')->first();
                                                        if ($billetera) { $set('new_bank_id', $billetera->id); }
                                                    }
                                                }
                                            })
                                            ->required(fn(Get $get) => $get('destination_type') === 'new'),
                                        Forms\Components\Select::make('new_bank_id')
                                            ->label('Banco / Billetera')
                                            ->options(\App\Models\Bank::where('is_active', true)->pluck('name', 'id'))
                                            ->searchable()->required(fn(Get $get) => $get('destination_type') === 'new'),
                                    ]),
                                ])->visible(fn(Get $get) => $get('condicion_venta') === 'contado' && $get('payment_method') === 'transfer'),

                                // CHEQUES
                                Forms\Components\Group::make()->schema([
                                    Forms\Components\Radio::make('check_type')
                                        ->label('Origen del Cheque')
                                        ->options(['tercero' => 'Usar Cheques de Terceros (En Cartera)', 'propio' => 'Emitir Cheque Propio (Nuevo)'])
                                        ->default('tercero')->inline()->live(),

                                    Forms\Components\Select::make('check_origin_filter')
                                        ->label('Filtro para el buscador')
                                        ->options(['all' => 'Buscar en Todos', 'Fiscal' => 'Buscar solo Blancos (Fiscal)', 'Internal' => 'Buscar solo Negros (Interno)'])
                                        ->default(fn(Get $get) => $get('tipo_comprobante') === 'X' ? 'Internal' : 'Fiscal') // Auto-selecciona basado en el tipo de factura
                                        ->visible(fn(Get $get) => $get('check_type') === 'tercero')->live(),

                                    Forms\Components\Select::make('selected_checks')
                                        ->label('Buscar y Agregar Cheques')
                                        ->visible(fn(Get $get) => $get('check_type') === 'tercero')
                                        ->multiple()->searchable()->preload()
                                        ->placeholder('Hacé clic para ver los cheques o escribí para buscar...')
                                        ->options(function (Get $get) {
                                            $query = \App\Models\Check::where('status', 'InPortfolio')->with('client');
                                            if ($get('check_origin_filter') !== 'all') {
                                                $query->where('origin', $get('check_origin_filter'));
                                            }
                                            return $query->get()->mapWithKeys(function($check) {
                                                $tipo = $check->origin === 'Fiscal' ? 'BLANCO' : 'NEGRO';
                                                $echeq = $check->is_echeq ? 'ECHEQ' : 'Físico';
                                                $banco = $check->bank_name ?? 'Banco Desconocido';
                                                $cliente = $check->client ? $check->client->name : 'Sin Cliente';
                                                $fecha = $check->payment_date ? $check->payment_date->format('d/m/Y') : 'Sin fecha';
                                                $label = "$ " . number_format($check->amount, 2, ',', '.') . " | Bco: {$banco} | Nro: {$check->number} | Vence: {$fecha} | De: {$cliente} | [{$tipo} - {$echeq}]";
                                                return [$check->id => $label];
                                            });
                                        })
                                        ->live()
                                        ->afterStateUpdated(function (Get $get, Set $set, $state) {
                                            $sum = \App\Models\Check::whereIn('id', $state ?? [])->sum('amount');
                                            $set('amount_paid', $sum);
                                        })->columnSpanFull(),

                                    Forms\Components\Grid::make(3)
                                        ->visible(fn(Get $get) => $get('check_type') === 'propio')
                                        ->schema([
                                            Forms\Components\Select::make('own_check_bank_id')->label('Nuestro Banco')->options(\App\Models\Bank::pluck('name', 'id'))->searchable()->required(fn(Get $get) => $get('check_type') === 'propio'),
                                            Forms\Components\TextInput::make('own_check_number')->label('Número del Cheque')->required(fn(Get $get) => $get('check_type') === 'propio'),
                                            Forms\Components\DatePicker::make('own_check_payment_date')->label('Fecha de Cobro')->required(fn(Get $get) => $get('check_type') === 'propio'),
                                            Forms\Components\Toggle::make('own_check_is_echeq')->label('Es E-Cheq')->default(true),
                                        ]),
                                ])->visible(fn(Get $get) => $get('condicion_venta') === 'contado' && $get('payment_method') === 'check'),
                            ]),
                    ])
                    ->action(function (array $data, Supplier $record) {
                        $isFiscal = $data['tipo_comprobante'] !== 'X';
                        $amount = (float) $data['total'];
                        $nroComprobante = str_pad($data['punto_vta'], 4, '0', STR_PAD_LEFT) . '-' . str_pad($data['numero'], 8, '0', STR_PAD_LEFT);

                        DB::transaction(function () use ($data, $record, $isFiscal, $amount, $nroComprobante) {
                            $origin = $isFiscal ? 'Fiscal' : 'Internal';

                            // 1. CARGAMOS LA DEUDA Y FACTURA
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

                            // 2. SI ES CONTADO, EFECTUAMOS EL PAGO INMEDIATO
                            if (isset($data['condicion_venta']) && $data['condicion_venta'] === 'contado') {
                                $paidAmount = (float) $data['amount_paid'];

                                // Descontamos la deuda que acabamos de cargar
                                if ($isFiscal) {
                                    $record->decrement('account_balance_fiscal', $paidAmount);
                                } else {
                                    $record->decrement('account_balance_internal', $paidAmount);
                                }

                                if ($data['payment_method'] === 'cash') {
                                    $account = \App\Models\CompanyAccount::find($data['company_account_id_cash']);
                                    if ($account) {
                                        $account->decrement('current_balance', $paidAmount);
                                        \App\Models\Transaction::create([
                                            'company_account_id' => $account->id,
                                            'supplier_id' => $record->id,
                                            'type' => 'Outcome',
                                            'amount' => $paidAmount,
                                            'description' => "Pago al Contado (Caja) Factura: {$nroComprobante}",
                                            'concept' => 'Pago a Proveedor', // <--- AGREGAR ESTA LÍNEA
                                            'origin' => $origin,
                                        ]);
                                    }
                                } elseif ($data['payment_method'] === 'transfer') {
                                    $account = \App\Models\CompanyAccount::find($data['company_account_id_bank']);
                                    if ($data['destination_type'] === 'new') {
                                        \App\Models\SupplierBankAccount::create([
                                            'supplier_id' => $record->id,
                                            'bank_id' => $data['new_bank_id'],
                                            'cbu_cvu' => $data['new_cbu_cvu'],
                                            'alias' => 'Guardado automático',
                                        ]);
                                    }
                                    if ($account) {
                                        $account->decrement('current_balance', $paidAmount);
                                        \App\Models\Transaction::create([
                                            'company_account_id' => $account->id,
                                            'supplier_id' => $record->id,
                                            'type' => 'Outcome',
                                            'amount' => $paidAmount,
                                            'description' => "Transf. al Contado Factura: {$nroComprobante}",
                                            'concept' => 'Pago a Proveedor', // <--- AGREGAR ESTA LÍNEA
                                            'origin' => $origin,
                                        ]);
                                    }
                                } elseif ($data['payment_method'] === 'check') {
                                    if ($data['check_type'] === 'tercero' && !empty($data['selected_checks'])) {
                                        \App\Models\Check::whereIn('id', $data['selected_checks'])->update([
                                            'status' => 'Delivered', 
                                            'supplier_id' => $record->id,
                                            'delivered_at' => now(),
                                        ]);
                                    } elseif ($data['check_type'] === 'propio') {
                                        \App\Models\Check::create([
                                            'supplier_id' => $record->id,
                                            'bank_id' => $data['own_check_bank_id'],
                                            'number' => $data['own_check_number'],
                                            'payment_date' => $data['own_check_payment_date'],
                                            'amount' => $paidAmount,
                                            'type' => 'Own',
                                            'origin' => $origin,
                                            'is_echeq' => $data['own_check_is_echeq'],
                                            'status' => 'Delivered', 
                                            'delivered_at' => now(),
                                        ]);
                                    }
                                }
                            }
                        });
                        Notification::make()->success()->title('Comprobante procesado correctamente')->send();
                    }),

                // --- BOTÓN PAGAR CLÁSICO (Para pagar deudas viejas) ---
                Tables\Actions\Action::make('register_payment')
                    ->label('Pagar')
                    ->icon('heroicon-o-banknotes')
                    ->color('danger')
                    ->modalWidth('4xl')
                    ->closeModalByClickingAway(false) // <--- MAGIA: No se cierra al hacer clic afuera
                    ->form([
                        Forms\Components\Section::make('Estrategia de Pago')->schema([
                            Forms\Components\Grid::make(3)->schema([
                                Forms\Components\Select::make('split_strategy')
                                    ->label('Imputar a (Blanco/Negro):')
                                    ->options(['fiscal_100' => 'Deuda Blanca (Fiscal)', 'internal_100' => 'Deuda Negra (Interna)'])
                                    ->default('fiscal_100')->live()->required(),
                                Forms\Components\Select::make('payment_method')
                                    ->label('Forma de Pago')
                                    ->options(['cash' => 'Efectivo (Caja)', 'transfer' => 'Transferencia Bancaria', 'check' => 'Pago con Cheques'])
                                    ->default('transfer')->live()->required(),
                                Forms\Components\TextInput::make('amount')
                                    ->label('Monto a Pagar')->numeric()->prefix('$')
                                    ->disabled(fn(Get $get) => $get('payment_method') === 'check' && $get('check_type') === 'tercero')
                                    ->dehydrated()->required(),
                            ]),
                        ]),

                        Forms\Components\Section::make('Pago en Efectivo')
                            ->visible(fn(Get $get) => $get('payment_method') === 'cash')
                            ->schema([
                                Forms\Components\Select::make('company_account_id_cash')->label('¿De qué Caja sale el efectivo?')
                                    ->options(\App\Models\CompanyAccount::where('type', 'cash')->pluck('name', 'id'))
                                    ->required(fn(Get $get) => $get('payment_method') === 'cash'),
                            ]),

                        Forms\Components\Section::make('Detalle de Transferencia')
                            ->visible(fn(Get $get) => $get('payment_method') === 'transfer')
                            ->schema([
                                Forms\Components\Select::make('company_account_id_bank')->label('Cuenta Origen (Tuya)')
                                    ->options(\App\Models\CompanyAccount::where('type', 'bank')->pluck('name', 'id'))
                                    ->required(fn(Get $get) => $get('payment_method') === 'transfer'),
                                
                                Forms\Components\Radio::make('destination_type')->label('Cuenta Destino (Proveedor)')
                                    ->options(['saved' => 'Elegir cuenta guardada', 'new' => 'Cargar un CBU/CVU nuevo ahora'])
                                    ->default('saved')->inline()->live(),

                                Forms\Components\Select::make('supplier_bank_account_id')->label('Seleccionar Cuenta del Proveedor')
                                    ->options(fn ($record) => $record->bankAccounts()->with('bank')->get()->mapWithKeys(fn($acc) => [$acc->id => "{$acc->bank->name} - {$acc->cbu_cvu}"]))
                                    ->visible(fn(Get $get) => $get('destination_type') === 'saved')
                                    ->required(fn(Get $get, $record) => $get('destination_type') === 'saved' && $record->bankAccounts->count() > 0),

                                Forms\Components\Grid::make(2)->visible(fn(Get $get) => $get('destination_type') === 'new')->schema([
                                    Forms\Components\TextInput::make('new_cbu_cvu')->label('Nuevo CBU / CVU')->length(22)->live(debounce: 500)
                                        ->afterStateUpdated(function (Set $set, $state) {
                                            if (strlen($state) >= 3) {
                                                $prefix = substr($state, 0, 3);
                                                $bank = \App\Models\Bank::where('code', $prefix)->first();
                                                if ($bank) { $set('new_bank_id', $bank->id); } 
                                                else {
                                                    $billetera = \App\Models\Bank::where('code', '000')->first();
                                                    if ($billetera) { $set('new_bank_id', $billetera->id); }
                                                }
                                            }
                                        })->required(fn(Get $get) => $get('destination_type') === 'new'),
                                    Forms\Components\Select::make('new_bank_id')->label('Banco / Billetera')
                                        ->options(\App\Models\Bank::where('is_active', true)->pluck('name', 'id'))
                                        ->searchable()->required(fn(Get $get) => $get('destination_type') === 'new'),
                                ]),
                            ]),

                        Forms\Components\Section::make('Gestión de Cheques')
                            ->visible(fn(Get $get) => $get('payment_method') === 'check')
                            ->schema([
                                Forms\Components\Radio::make('check_type')->label('Origen del Cheque')
                                    ->options(['tercero' => 'Usar Cheques de Terceros (En Cartera)', 'propio' => 'Emitir Cheque Propio (Nuevo)'])
                                    ->default('tercero')->inline()->live(),

                                Forms\Components\Select::make('check_origin_filter')->label('Filtro para el buscador')
                                    ->options(['all' => 'Buscar en Todos', 'Fiscal' => 'Buscar solo Blancos (Fiscal)', 'Internal' => 'Buscar solo Negros (Interno)'])
                                    ->default('all')->visible(fn(Get $get) => $get('check_type') === 'tercero')->live(),

                                Forms\Components\Select::make('selected_checks')->label('Buscar y Agregar Cheques')
                                    ->visible(fn(Get $get) => $get('check_type') === 'tercero')
                                    ->multiple()->searchable()->preload()
                                    ->placeholder('Hacé clic para ver los cheques o escribí para buscar...')
                                    ->options(function (Get $get) {
                                        $query = \App\Models\Check::where('status', 'InPortfolio')->with('client');
                                        if ($get('check_origin_filter') !== 'all') { $query->where('origin', $get('check_origin_filter')); }
                                        return $query->get()->mapWithKeys(function($check) {
                                            $tipo = $check->origin === 'Fiscal' ? 'BLANCO' : 'NEGRO';
                                            $echeq = $check->is_echeq ? 'ECHEQ' : 'Físico';
                                            $banco = $check->bank_name ?? 'Banco Desconocido';
                                            $cliente = $check->client ? $check->client->name : 'Sin Cliente';
                                            $fecha = $check->payment_date ? $check->payment_date->format('d/m/Y') : 'Sin fecha';
                                            $label = "$ " . number_format($check->amount, 2, ',', '.') . " | Bco: {$banco} | Nro: {$check->number} | Vence: {$fecha} | De: {$cliente} | [{$tipo} - {$echeq}]";
                                            return [$check->id => $label];
                                        });
                                    })->live()->afterStateUpdated(function (Get $get, Set $set, $state) {
                                        $sum = \App\Models\Check::whereIn('id', $state ?? [])->sum('amount');
                                        $set('amount', $sum);
                                    })->columnSpanFull(),

                                Forms\Components\Grid::make(3)->visible(fn(Get $get) => $get('check_type') === 'propio')->schema([
                                    Forms\Components\Select::make('own_check_bank_id')->label('Nuestro Banco')->options(\App\Models\Bank::pluck('name', 'id'))->searchable()->required(fn(Get $get) => $get('check_type') === 'propio'),
                                    Forms\Components\TextInput::make('own_check_number')->label('Número del Cheque')->required(fn(Get $get) => $get('check_type') === 'propio'),
                                    Forms\Components\DatePicker::make('own_check_payment_date')->label('Fecha de Cobro')->required(fn(Get $get) => $get('check_type') === 'propio'),
                                    Forms\Components\Toggle::make('own_check_is_echeq')->label('Es E-Cheq')->default(true),
                                ]),
                            ]),
                    ])
                    ->action(function (array $data, $record) {
                        $amount = (float) $data['amount'];
                        $strategy = $data['split_strategy'];

                        \Illuminate\Support\Facades\DB::transaction(function () use ($data, $record, $amount, $strategy) {
                            if ($strategy === 'fiscal_100') {
                                $record->decrement('account_balance_fiscal', $amount);
                                $origin = 'Fiscal';
                            } else {
                                $record->decrement('account_balance_internal', $amount);
                                $origin = 'Internal';
                            }

                            if ($data['payment_method'] === 'cash') {
                                $account = \App\Models\CompanyAccount::find($data['company_account_id_cash']);
                                if ($account) {
                                    $account->decrement('current_balance', $amount);
                                    \App\Models\Transaction::create([
                                        'company_account_id' => $account->id,
                                        'supplier_id' => $record->id,
                                        'type' => 'Outcome',
                                        'amount' => $amount,
                                        'description' => "Pago en Efectivo a {$record->name}",
                                        'concept' => 'Pago a Proveedor', // <--- AGREGAR ESTA LÍNEA
                                        'origin' => $origin,
                                    ]);
                                }
                            } elseif ($data['payment_method'] === 'transfer') {
                                $account = \App\Models\CompanyAccount::find($data['company_account_id_bank']);
                                if ($data['destination_type'] === 'new') {
                                    \App\Models\SupplierBankAccount::create([
                                        'supplier_id' => $record->id,
                                        'bank_id' => $data['new_bank_id'],
                                        'cbu_cvu' => $data['new_cbu_cvu'],
                                        'alias' => 'Guardado automático',
                                    ]);
                                }
                                if ($account) {
                                    $account->decrement('current_balance', $amount);
                                    \App\Models\Transaction::create([
                                        'company_account_id' => $account->id,
                                        'supplier_id' => $record->id,
                                        'type' => 'Outcome',
                                        'amount' => $amount,
                                        'description' => "Transferencia a {$record->name}",
                                        'concept' => 'Pago a Proveedor', // <--- AGREGAR ESTA LÍNEA
                                        'origin' => $origin,
                                    ]);
                                }
                            } elseif ($data['payment_method'] === 'check') {
                                if ($data['check_type'] === 'tercero' && !empty($data['selected_checks'])) {
                                    \App\Models\Check::whereIn('id', $data['selected_checks'])->update([
                                        'status' => 'Delivered', 
                                        'supplier_id' => $record->id,
                                        'delivered_at' => now(),
                                    ]);
                                } elseif ($data['check_type'] === 'propio') {
                                    \App\Models\Check::create([
                                        'supplier_id' => $record->id,
                                        'bank_id' => $data['own_check_bank_id'],
                                        'number' => $data['own_check_number'],
                                        'payment_date' => $data['own_check_payment_date'],
                                        'amount' => $amount,
                                        'type' => 'Own',
                                        'origin' => $origin,
                                        'is_echeq' => $data['own_check_is_echeq'],
                                        'status' => 'Delivered', 
                                        'delivered_at' => now(),
                                    ]);
                                }
                            }
                        });

                        \Filament\Notifications\Notification::make()->success()->title('Pago procesado correctamente.')->send();
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
            SupplierInvoicesRelationManager::class,
            ActivitiesRelationManager::class,
            TransactionsRelationManager::class,
        ];
    }
}