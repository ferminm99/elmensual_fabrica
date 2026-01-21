<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ClientResource\Pages;
use App\Filament\Resources\ClientResource\RelationManagers\PaymentsRelationManager;
use App\Filament\Resources\ClientResource\RelationManagers\OrdersRelationManager;
use App\Filament\Resources\ProductionOrderResource\RelationManagers\ActivitiesRelationManager;
use App\Models\Client;
use App\Models\CompanyAccount;
use App\Models\Transaction;
use App\Models\Check;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;

class ClientResource extends Resource
{
    protected static ?string $model = Client::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';
    
    protected static ?string $modelLabel = 'Cliente';
    protected static ?string $pluralModelLabel = 'Clientes';
    protected static ?string $navigationGroup = 'Ventas';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Datos del Cliente')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nombre / Razón Social')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('tax_id')
                            ->label('CUIT / DNI')
                            ->maxLength(20),
                        
                        Forms\Components\TextInput::make('email')
                            ->email()
                            ->maxLength(255),
                        
                        Forms\Components\TextInput::make('default_discount')
                            ->label('Descuento Fijo (%)')
                            ->numeric()
                            ->suffix('%')
                            ->default(0),

                        Forms\Components\TextInput::make('phone')
                            ->label('Teléfono')
                            ->tel()
                            ->maxLength(255),
                            
                        Forms\Components\TextInput::make('address')
                            ->label('Dirección')
                            ->maxLength(255)
                            ->columnSpanFull(),
                    ])->columns(2),

                Forms\Components\Section::make('Saldos de Cuenta Corriente')
                    ->schema([
                        Forms\Components\TextInput::make('fiscal_debt')
                            ->label('Saldo Blanco (Fiscal)')
                            ->prefix('$')
                            ->numeric()
                            ->default(0),

                        Forms\Components\TextInput::make('internal_debt')
                            ->label('Saldo Negro (Interno)')
                            ->prefix('$')
                            ->numeric()
                            ->default(0),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Cliente')
                    ->searchable()
                    ->weight('bold')
                    ->description(fn (Client $record) => "Desc: {$record->default_discount}%"),

                // 1. DEUDA BLANCA (Nombres reales de la DB)
                Tables\Columns\TextColumn::make('fiscal_debt') 
                    ->label('Deuda Fiscal')
                    ->money('ARS')
                    ->sortable()
                    ->color(fn (string $state): string => $state > 0 ? 'danger' : 'success'),

                // 2. DEUDA NEGRA (Nombres reales de la DB)
                Tables\Columns\TextColumn::make('internal_debt')
                    ->label('Deuda Interna')
                    ->money('ARS')
                    ->color('warning')
                    ->toggleable(isToggledHiddenByDefault: false),

                // 3. DEUDA REAL (Suma de las dos columnas reales)
                Tables\Columns\TextColumn::make('real_balance')
                    ->label('TOTAL REAL')
                    ->money('ARS')
                    ->state(fn (Client $record) => $record->fiscal_debt + $record->internal_debt)
                    ->weight('black')
                    ->color('danger'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),

                // --- BOTÓN DE COBRO ---
                Tables\Actions\Action::make('register_payment')
                    ->label('Cobrar')
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->modalWidth('2xl')
                    ->form([
                        // SECCIÓN 1: ESTRATEGIA (Sin cambios)
                        Forms\Components\Section::make('Estrategia de Cobro')
                            ->schema([
                                Forms\Components\TextInput::make('amount')
                                    ->label('Monto TOTAL Recibido')
                                    ->numeric()
                                    ->prefix('$')
                                    ->required(),
                                Forms\Components\Select::make('split_strategy')
                                    ->label('Imputación')
                                    ->options([
                                        'fiscal_100' => '100% Fiscal (Blanco)',
                                        'split_50_50' => 'Mix 50% / 50%',
                                        'internal_100' => '100% Interno (Negro)',
                                    ])
                                    ->default('fiscal_100')
                                    ->required(),
                            ])->columns(2),

                        // SECCIÓN 2: FORMA DE PAGO
                        Forms\Components\Section::make('Forma de Pago')
                            ->schema([
                                Forms\Components\Select::make('payment_method')
                                    ->label('Método')
                                    ->options([
                                        'cash' => 'Efectivo',
                                        'transfer' => 'Transferencia',
                                        'check' => 'Cheque', 
                                    ])
                                    ->live()
                                    ->afterStateUpdated(function ($state, callable $set, Client $record) {
                                        // AUTOCOMPLETADO INTELIGENTE
                                        if ($state === 'transfer') {
                                            $lastTx = Transaction::where('description', 'like', "%{$record->name}%")
                                                ->whereNotNull('payment_details->client_cbu')
                                                ->latest()
                                                ->first();

                                            if ($lastTx) {
                                                $details = $lastTx->payment_details;
                                                $set('client_bank', $details['client_bank'] ?? '');
                                                $set('client_cbu', $details['client_cbu'] ?? '');
                                                $set('client_alias', $details['client_alias'] ?? '');
                                            }
                                        }
                                    })
                                    ->required(),

                                // --- CAMPOS DE TRANSFERENCIA (2 COLUMNAS, ORDENADO) ---
                                Forms\Components\Group::make([
                                    Forms\Components\Grid::make(2)->schema([
                                        // FILA 1
                                        Forms\Components\TextInput::make('client_bank')
                                            ->label('Banco Origen')
                                            ->placeholder('Ej: Galicia'),
                                        
                                        Forms\Components\TextInput::make('transfer_id')
                                            ->label('ID Comprobante')
                                            ->placeholder('Opcional'), // Ya no es required

                                        // FILA 2
                                        Forms\Components\TextInput::make('client_cbu')
                                            ->label('CBU Origen')
                                            ->numeric()
                                            // Obligatorio solo si no puso Alias
                                            ->requiredWithout('client_alias') 
                                            ->validationAttribute('CBU'),

                                        Forms\Components\TextInput::make('client_alias')
                                            ->label('Alias Origen')
                                            // Obligatorio solo si no puso CBU
                                            ->requiredWithout('client_cbu')
                                            ->validationAttribute('Alias'),
                                    ]),
                                ])->visible(fn (Forms\Get $get) => $get('payment_method') === 'transfer'),

                                // --- CAMPOS DE CHEQUE (Sin cambios) ---
                                Forms\Components\Group::make([
                                    Forms\Components\Grid::make(2)->schema([
                                        Forms\Components\TextInput::make('check_bank')->label('Banco')->required(),
                                        Forms\Components\TextInput::make('check_number')->label('N° Cheque')->required(),
                                        Forms\Components\DatePicker::make('check_payment_date')->label('Fecha Cobro')->required()->default(now()->addDays(30)),
                                        Forms\Components\TextInput::make('check_owner')->label('Firmante / CUIT')->required(),
                                        Forms\Components\Toggle::make('is_echeq')->label('Es E-Cheq'),
                                    ]),
                                ])->visible(fn (Forms\Get $get) => $get('payment_method') === 'check'),

                                // CUENTA DESTINO
                                Forms\Components\Select::make('company_account_id')
                                    ->label('Cuenta Destino (Caja/Banco)')
                                    ->options(CompanyAccount::all()->pluck('name', 'id'))
                                    ->visible(fn (Forms\Get $get) => in_array($get('payment_method'), ['cash', 'transfer']))
                                    ->required(fn (Forms\Get $get) => in_array($get('payment_method'), ['cash', 'transfer'])),
                                
                                Forms\Components\Textarea::make('notes')->label('Notas'),
                            ]),
                    ])
                    ->action(function (array $data, Client $record) {
                        $totalAmount = $data['amount'];
                        $strategy = $data['split_strategy'];

                        // 1. IMPUTACIÓN DE DEUDA GLOBAL (Esto lo mantenemos igual, es tu saldo general)
                        $amountFiscal = 0; $amountInternal = 0;
                        if ($strategy === 'fiscal_100') $amountFiscal = $totalAmount;
                        elseif ($strategy === 'internal_100') $amountInternal = $totalAmount;
                        elseif ($strategy === 'split_50_50') { $amountFiscal = $totalAmount/2; $amountInternal = $totalAmount/2; }

                        if ($amountFiscal > 0) {
                            $record->decrement('fiscal_debt', $amountFiscal);
                            // ---> MAGIA FIFO FISCAL <---
                            $record->distributePayment($amountFiscal, 'Fiscal');
                        }
                        
                        if ($amountInternal > 0) {
                            $record->decrement('internal_debt', $amountInternal);
                            // ---> MAGIA FIFO INTERNA <---
                            $record->distributePayment($amountInternal, 'Internal');
                        }
                        // 2. REGISTRAR EL PAGO
                        if ($data['payment_method'] === 'check') {
                            
                            // A. CREAR EL CHEQUE FÍSICO (Para la Cartera)
                            $check = Check::create([
                                'client_id' => $record->id,
                                'bank_name' => $data['check_bank'],
                                'number' => $data['check_number'],
                                'owner' => $data['check_owner'],
                                'amount' => $totalAmount,
                                'payment_date' => $data['check_payment_date'],
                                'status' => 'InPortfolio',
                                'type' => 'ThirdParty',
                                'is_echeq' => $data['is_echeq'] ?? false,
                            ]);

                            // B. CREAR LA TRANSACCIÓN (Para el Historial del Cliente) <-- ESTO FALTABA
                            Transaction::create([
                                'client_id' => $record->id,
                                'company_account_id' => null, // No está en banco, está en mano
                                'type' => 'Income',
                                'amount' => $totalAmount,
                                'description' => "Pago con Cheque #{$data['check_number']} ({$data['check_bank']})",
                                'concept' => 'Cobro Cliente',
                                'origin' => $strategy === 'internal_100' ? 'Internal' : 'Fiscal',
                                'payment_details' => ['check_id' => $check->id], // Vinculamos ID
                            ]);

                            Notification::make()->title('Cheque registrado en Cartera e Historial')->success()->send();

                        } else {
                            // PAGO CAJA / TRANSFERENCIA
                            $account = CompanyAccount::find($data['company_account_id']);
                            
                            $details = [];
                            if ($data['payment_method'] === 'transfer') {
                                $details = [
                                    'client_bank' => $data['client_bank'] ?? null,
                                    'client_cbu' => $data['client_cbu'] ?? null,
                                    'client_alias' => $data['client_alias'] ?? null,
                                    'transfer_id' => $data['transfer_id'] ?? null
                                ];
                            }

                            Transaction::create([
                                'company_account_id' => $account->id,
                                'client_id' => $record->id,
                                'type' => 'Income',
                                'amount' => $totalAmount,
                                'description' => "Cobro a {$record->name} ({$strategy})",
                                'concept' => 'Cobro Cliente',
                                'origin' => $strategy === 'internal_100' ? 'Internal' : 'Fiscal',
                                'payment_details' => $details, 
                            ]);

                            $account->increment('current_balance', $totalAmount);
                            
                            Notification::make()->title('Pago registrado en cuenta')->success()->send();
                        }
                    }),
        ]);
}

    public static function getRelations(): array
    {
        return [
            OrdersRelationManager::class,
            ActivitiesRelationManager::class,
            PaymentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListClients::route('/'),
            'create' => Pages\CreateClient::route('/create'),
            'edit' => Pages\EditClient::route('/{record}/edit'),
        ];
    }
}