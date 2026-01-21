<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SupplierResource\Pages;
use App\Filament\Resources\ProductionOrderResource\RelationManagers\ActivitiesRelationManager;
use App\Models\Supplier;
use App\Models\CompanyAccount;
use App\Models\Transaction;
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
                    ->color('danger')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),

                // --- BOTÓN PAGAR (SALIDA DE DINERO) ---
                Tables\Actions\Action::make('register_payment')
                    ->label('Pagar') // Diferencia clave: Acá PAGAMOS
                    ->icon('heroicon-o-credit-card')
                    ->color('danger') // Rojo porque sale plata
                    ->modalWidth('2xl')
                    ->form([
                        Forms\Components\Section::make('Estrategia de Pago')
                            ->schema([
                                Forms\Components\TextInput::make('amount')
                                    ->label('Monto a Pagar')
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

                        Forms\Components\Section::make('Egreso de Tesorería')
                            ->schema([
                                Forms\Components\Select::make('payment_method')
                                    ->label('Forma de Pago')
                                    ->options([
                                        'cash' => 'Efectivo',
                                        'transfer' => 'Transferencia Bancaria',
                                        // Aquí podríamos agregar "Cheque Propio" en el futuro
                                    ])
                                    ->reactive()
                                    ->required(),

                                Forms\Components\Select::make('company_account_id')
                                    ->label('¿De qué cuenta sale el dinero?')
                                    ->options(CompanyAccount::all()->pluck('name', 'id'))
                                    ->required(),
                                
                                Forms\Components\Textarea::make('notes')
                                    ->label('Observaciones')
                                    ->placeholder('Ej: Pago factura 001...'),
                            ]),
                    ])
                    ->action(function (array $data, Supplier $record) {
                        $amount = $data['amount'];
                        $strategy = $data['split_strategy'];

                        // 1. Descontar nuestra deuda (Al pagar, la deuda BAJA)
                        $fiscalPart = 0; $internalPart = 0;

                        if ($strategy === 'fiscal_100') $fiscalPart = $amount;
                        elseif ($strategy === 'internal_100') $internalPart = $amount;
                        else { $fiscalPart = $amount / 2; $internalPart = $amount / 2; }

                        if ($fiscalPart > 0) $record->decrement('account_balance_fiscal', $fiscalPart);
                        if ($internalPart > 0) $record->decrement('account_balance_internal', $internalPart);

                        // 2. Registrar Salida de Dinero (Transaction)
                        $account = CompanyAccount::find($data['company_account_id']);
                        
                        Transaction::create([
                            'company_account_id' => $account->id,
                            'type' => 'Expense', // <--- IMPORTANTE: Es Gasto (Egreso)
                            'amount' => $amount,
                            'description' => "Pago a {$record->name}",
                            'concept' => "Pago Proveedores",
                            'origin' => $strategy === 'internal_100' ? 'Internal' : 'Fiscal',
                            'payment_details' => ['method' => $data['payment_method'], 'notes' => $data['notes']],
                        ]);

                        // Restamos plata de la caja/banco
                        $account->decrement('current_balance', $amount);

                        Notification::make()->title('Pago registrado')->success()->send();
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
        ];
    }
}