<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ClientResource\Pages;
use App\Filament\Resources\ClientResource\RelationManagers\OrdersRelationManager;
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
                        Forms\Components\TextInput::make('account_balance_fiscal')
                            ->label('Saldo Blanco (Fiscal)')
                            ->prefix('$')
                            ->numeric()
                            ->default(0),

                        Forms\Components\TextInput::make('account_balance_internal')
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

                // 1. DEUDA BLANCA
                Tables\Columns\TextColumn::make('account_balance_fiscal')
                    ->label('Deuda Fiscal')
                    ->money('ARS')
                    ->sortable()
                    ->color(fn (string $state): string => $state > 0 ? 'danger' : 'success'),

                // 2. DEUDA NEGRA (OCULTA POR DEFECTO)
                Tables\Columns\TextColumn::make('account_balance_internal')
                    ->label('Deuda Interna')
                    ->money('ARS')
                    ->color('warning')
                    ->toggleable(isToggledHiddenByDefault: true), 

                // 3. DEUDA REAL (SUMA)
                Tables\Columns\TextColumn::make('real_balance')
                    ->label('TOTAL REAL')
                    ->money('ARS')
                    ->state(fn (Client $record) => $record->account_balance_fiscal + $record->account_balance_internal)
                    ->weight('black')
                    ->color('danger')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),

                // --- BOTÓN DE COBRO DEFINITIVO (ESTRATEGIA + DETALLE TÉCNICO) ---
                Tables\Actions\Action::make('register_payment')
                    ->label('Cobrar')
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->modalWidth('2xl')
                    ->form([
                        // SECCIÓN 1: CUÁNTO Y CÓMO IMPUTAMOS
                        Forms\Components\Section::make('Estrategia de Cobro')
                            ->description('Define el monto y cómo afecta a la cuenta corriente.')
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
                                        'split_50_50' => 'Mix 50% / 50% (Mitad y Mitad)',
                                        'internal_100' => '100% Interno (Negro)',
                                    ])
                                    ->default('fiscal_100')
                                    ->required(),
                            ])->columns(2),

                        // SECCIÓN 2: DETALLES DEL DINERO (ORIGEN Y DESTINO)
                        Forms\Components\Section::make('Ingreso de Dinero (Tesorería)')
                            ->schema([
                                Forms\Components\Select::make('payment_method')
                                    ->label('Forma de Pago')
                                    ->options([
                                        'cash' => 'Efectivo',
                                        'transfer' => 'Transferencia Bancaria',
                                        'check' => 'Cheque Físico / E-Cheq',
                                    ])
                                    ->reactive()
                                    ->required(),

                                // --- DATOS ESPECÍFICOS DE TRANSFERENCIA ---
                                Forms\Components\Group::make([
                                    Forms\Components\Grid::make(2)->schema([
                                        Forms\Components\TextInput::make('client_bank')
                                            ->label('Banco Origen (Cliente)')
                                            ->placeholder('Ej: Galicia'),
                                        Forms\Components\TextInput::make('transfer_id')
                                            ->label('ID Comprobante')
                                            ->required(),
                                        Forms\Components\TextInput::make('client_cbu')
                                            ->label('CBU / Alias Origen')
                                            ->columnSpanFull(),
                                    ]),
                                ])->visible(fn (Forms\Get $get) => $get('payment_method') === 'transfer'),

                                // --- DATOS ESPECÍFICOS DE CHEQUES ---
                                Forms\Components\Group::make([
                                    Forms\Components\Grid::make(2)->schema([
                                        Forms\Components\TextInput::make('check_bank')
                                            ->label('Banco del Cheque')
                                            ->required(),
                                        Forms\Components\TextInput::make('check_number')
                                            ->label('N° Cheque')
                                            ->required(),
                                        Forms\Components\DatePicker::make('check_due_date')
                                            ->label('Fecha de Cobro')
                                            ->required(),
                                        Forms\Components\TextInput::make('check_owner')
                                            ->label('Firmante / CUIT')
                                            ->required(),
                                        Forms\Components\Toggle::make('is_echeq')
                                            ->label('Es E-Cheq')
                                            ->inline(false),
                                    ]),
                                ])->visible(fn (Forms\Get $get) => $get('payment_method') === 'check'),

                                // --- DESTINO (NUESTRA CUENTA) ---
                                Forms\Components\Select::make('company_account_id')
                                    ->label('Cuenta Destino (Caja/Banco)')
                                    ->options(CompanyAccount::all()->pluck('name', 'id'))
                                    ->visible(fn (Forms\Get $get) => in_array($get('payment_method'), ['cash', 'transfer']))
                                    ->required(fn (Forms\Get $get) => in_array($get('payment_method'), ['cash', 'transfer'])),
                                
                                Forms\Components\Textarea::make('notes')
                                    ->label('Notas Internas')
                                    ->placeholder('Observaciones...'),
                            ]),
                    ])
                    ->action(function (array $data, Client $record) {
                        $totalAmount = $data['amount'];
                        $strategy = $data['split_strategy'];

                        // 1. CÁLCULO MATEMÁTICO DE LA ESTRATEGIA
                        $amountFiscal = 0;
                        $amountInternal = 0;

                        if ($strategy === 'fiscal_100') {
                            $amountFiscal = $totalAmount;
                        } elseif ($strategy === 'internal_100') {
                            $amountInternal = $totalAmount;
                        } elseif ($strategy === 'split_50_50') {
                            $amountFiscal = $totalAmount / 2;
                            $amountInternal = $totalAmount / 2;
                        }

                        // 2. DESCONTAR DEUDA
                        if ($amountFiscal > 0) $record->decrement('account_balance_fiscal', $amountFiscal);
                        if ($amountInternal > 0) $record->decrement('account_balance_internal', $amountInternal);
                        
                        // 3. REGISTRAR TRANSACCIÓN (Efectivo/Transferencia)
                        if (in_array($data['payment_method'], ['cash', 'transfer'])) {
                            $account = CompanyAccount::find($data['company_account_id']);
                            
                            // Preparamos los detalles extra (JSON)
                            $details = [];
                            if ($data['payment_method'] === 'transfer') {
                                $details = [
                                    'client_bank' => $data['client_bank'] ?? null,
                                    'transfer_id' => $data['transfer_id'] ?? null,
                                    'client_cbu'  => $data['client_cbu'] ?? null,
                                ];
                            }

                            Transaction::create([
                                'company_account_id' => $account->id,
                                'type' => 'Income',
                                'amount' => $totalAmount,
                                'description' => "Cobro a {$record->name} ({$strategy})",
                                'concept' => "Cobro Cliente", // Para que no chille MySQL
                                'origin' => $strategy === 'internal_100' ? 'Internal' : 'Fiscal',
                                'payment_details' => $details, // <--- Aquí guardamos la info técnica
                            ]);

                            $account->increment('current_balance', $totalAmount);
                        }

                        // 4. REGISTRAR CHEQUE (Si aplica)
                        if ($data['payment_method'] === 'check') {
                             Check::create([
                                'type' => 'ThirdParty',
                                'status' => 'InPortfolio', 
                                'amount' => $totalAmount,
                                'bank_name' => $data['check_bank'],
                                'number' => $data['check_number'],
                                'due_date' => $data['check_due_date'],
                                'owner' => $data['check_owner'],
                                'client_id' => $record->id,
                                // Podrías agregar 'is_echeq' si lo tienes en la BD
                            ]);
                        }

                        Notification::make()
                            ->title('Cobro registrado correctamente')
                            ->body("Se imputaron $ " . number_format($amountFiscal, 0) . " al Blanco y $ " . number_format($amountInternal, 0) . " al Negro.")
                            ->success()
                            ->send();
                    }),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            OrdersRelationManager::class,
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