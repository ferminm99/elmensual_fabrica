<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CheckResource\Pages;
use App\Models\Check;
use App\Models\CompanyAccount;
use App\Models\Transaction;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;

class CheckResource extends Resource
{
    protected static ?string $model = Check::class;
    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $modelLabel = 'Cheque';
    protected static ?string $pluralModelLabel = 'Cartera de Cheques';
    protected static ?string $navigationGroup = 'Tesorería';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Datos del Cheque')
                    ->schema([
                        Forms\Components\TextInput::make('bank_name')
                            ->label('Banco Emisor')
                            ->required(),
                        Forms\Components\TextInput::make('number')
                            ->label('Número de Cheque')
                            ->required(),
                        Forms\Components\TextInput::make('owner')
                            ->label('Firmante / CUIT'),
                        
                        // CORRECCIÓN 1: Usamos 'payment_date' que es la OBLIGATORIA en tu DB
                        Forms\Components\DatePicker::make('payment_date')
                            ->label('Fecha de Cobro')
                            ->required(),

                        Forms\Components\TextInput::make('amount')
                            ->label('Monto')
                            ->prefix('$')
                            ->numeric()
                            ->required(),
                        
                        Forms\Components\Select::make('client_id')
                            ->relationship('client', 'name')
                            ->label('Cliente que entregó')
                            ->searchable(),

                        Forms\Components\Toggle::make('is_echeq')
                            ->label('Es E-Cheq')
                            ->columnSpanFull(),
                            
                        Forms\Components\Select::make('status')
                            ->label('Estado')
                            ->options([
                                'InPortfolio' => 'En Cartera (Mano)',
                                'Deposited' => 'Depositado',
                                'Used' => 'Entregado a Proveedor',
                                'Rejected' => 'Rechazado',
                            ])
                            ->default('InPortfolio')
                            ->required(),
                    ])->columns(2)
            ]);
    }

    public static function table(Table $table): Table
{
    return $table
        ->columns([
            Tables\Columns\TextColumn::make('number')
                ->label('N° Cheque')
                ->searchable()
                ->weight('bold'),
            
            Tables\Columns\TextColumn::make('bank_name')
                ->label('Banco'),
            
            Tables\Columns\TextColumn::make('amount')
                ->label('Monto')
                ->money('ARS')
                ->weight('bold'),
            
            Tables\Columns\TextColumn::make('payment_date')
                ->label('Fecha Cobro')
                ->date('d/m/Y')
                ->sortable()
                ->color(fn ($state) => $state <= now() ? 'danger' : 'gray'),
            
            Tables\Columns\TextColumn::make('client.name')
                ->label('De Cliente')
                ->searchable(),

            // --- CORRECCIÓN DEL ERROR DE TIPO (STATUS) ---
            Tables\Columns\TextColumn::make('status')
                ->label('Estado')
                ->badge()
                // Quitamos 'string' de la definición de la función para que no chille
                ->color(fn ($state) => match ($state->name ?? $state) {
                    'InPortfolio' => 'info',      // Azul
                    'Deposited' => 'success',     // Verde
                    'Used' => 'warning',          // Naranja
                    'Rejected' => 'danger',       // Rojo
                    default => 'gray',
                })
                ->formatStateUsing(fn ($state) => match ($state->name ?? $state) {
                    'InPortfolio' => 'En Cartera',
                    'Deposited' => 'Depositado',
                    'Used' => 'Entregado',
                    'Rejected' => 'Rechazado',
                    default => $state,
                }),
        ])
        ->defaultSort('created_at', 'desc') // Ordenar por más nuevos
        ->filters([
            Tables\Filters\SelectFilter::make('status')
                ->options([
                    'InPortfolio' => 'En Cartera',
                    'Deposited' => 'Depositados',
                    'Used' => 'Entregados',
                    'Rejected' => 'Rechazados',
                ])
                ->default('InPortfolio'),
        ])
        ->actions([
            // ACCIÓN: DEPOSITAR (Mueve la plata del cheque a tu Banco)
            Tables\Actions\Action::make('deposit')
                ->label('Depositar')
                ->icon('heroicon-o-arrow-right-circle')
                ->color('success')
                // Solo mostramos si está "En Cartera"
                ->visible(fn (Check $record) => ($record->status->name ?? $record->status) === 'InPortfolio')
                ->form([
                    Forms\Components\Select::make('company_account_id')
                        ->label('¿A qué Banco lo depositas?')
                        // Solo mostramos cuentas tipo 'bank'
                        ->options(CompanyAccount::where('type', 'bank')->pluck('name', 'id'))
                        ->required(),
                    Forms\Components\DatePicker::make('deposited_at')
                        ->label('Fecha de Depósito')
                        ->default(now())
                        ->required(),
                ])
                ->action(function (array $data, Check $record) {
                    $bankAccount = CompanyAccount::find($data['company_account_id']);
                    
                    if (!$bankAccount) return;

                    // 1. Crear movimiento de entrada en el Banco
                    Transaction::create([
                        'company_account_id' => $bankAccount->id,
                        'type' => 'Income',
                        'amount' => $record->amount,
                        'description' => "Depósito Cheque N° {$record->number} ({$record->bank_name})",
                        'concept' => 'Depósito Cheque',
                        'origin' => 'Fiscal', 
                        'payment_details' => ['check_id' => $record->id],
                    ]);

                    // 2. Sumar saldo al banco
                    $bankAccount->increment('current_balance', $record->amount);

                    // 3. Actualizar cheque a DEPOSITADO
                    $record->update([
                        'status' => 'Deposited', // O CheckStatus::Deposited si usas el enum
                        'deposited_at' => $data['deposited_at']
                    ]);

                    Notification::make()->title('Cheque depositado correctamente')->success()->send();
                }),
        ]);
}
    
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListChecks::route('/'),
            'create' => Pages\CreateCheck::route('/create'),
            'edit' => Pages\EditCheck::route('/{record}/edit'),
        ];
    }
}