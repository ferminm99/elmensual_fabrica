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
use Illuminate\Database\Eloquent\Builder;

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
                        Forms\Components\TextInput::make('bank_name')->label('Banco Emisor')->required(),
                        Forms\Components\TextInput::make('number')->label('Número de Cheque')->required(),
                        Forms\Components\TextInput::make('owner')->label('Firmante / CUIT'),
                        Forms\Components\DatePicker::make('due_date')->label('Fecha de Cobro')->required(),
                        Forms\Components\TextInput::make('amount')->label('Monto')->prefix('$')->numeric()->required(),
                        Forms\Components\Select::make('client_id')->relationship('client', 'name')->label('Cliente que entregó'),
                        Forms\Components\Select::make('status')
                            ->options([
                                'InPortfolio' => 'En Cartera (Mano)',
                                'Deposited' => 'Depositado',
                                'Used' => 'Entregado a Proveedor',
                                'Rejected' => 'Rechazado',
                            ])->default('InPortfolio'),
                    ])->columns(2)
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('number')->label('N° Cheque')->searchable()->weight('bold'),
                Tables\Columns\TextColumn::make('bank_name')->label('Banco'),
                Tables\Columns\TextColumn::make('amount')->label('Monto')->money('ARS')->weight('bold'),
                Tables\Columns\TextColumn::make('due_date')->label('Fecha Cobro')->date('d/m/Y')->sortable()
                    ->color(fn ($state) => $state <= now() ? 'danger' : 'gray'), // Rojo si ya venció
                
                Tables\Columns\TextColumn::make('client.name')->label('De Cliente'),

                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'InPortfolio' => 'info',      // Azul: Lo tengo yo
                        'Deposited' => 'success',     // Verde: Ya está en el banco
                        'Used' => 'warning',          // Naranja: Se lo di a un proveedor
                        'Rejected' => 'danger',       // Rojo: Rebotado
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'InPortfolio' => 'En Cartera',
                        'Deposited' => 'Depositado',
                        'Used' => 'Entregado',
                        'Rejected' => 'Rechazado',
                        default => $state,
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'InPortfolio' => 'En Cartera',
                        'Deposited' => 'Depositados',
                        'Used' => 'Entregados',
                    ])
                    ->default('InPortfolio'), // Por defecto vemos solo los que tenemos en mano
            ])
            ->actions([
                // --- ACCIÓN: DEPOSITAR CHEQUE ---
                Tables\Actions\Action::make('deposit')
                    ->label('Depositar')
                    ->icon('heroicon-o-arrow-right-circle')
                    ->color('success')
                    // Solo mostramos el botón si el cheque está "En Cartera"
                    ->visible(fn (Check $record) => $record->status === 'InPortfolio')
                    ->form([
                        Forms\Components\Select::make('company_account_id')
                            ->label('¿A qué Banco lo depositas?')
                            ->options(CompanyAccount::where('type', 'bank')->pluck('name', 'id')) // Solo mostramos Bancos
                            ->required(),
                        Forms\Components\DatePicker::make('deposited_at')
                            ->label('Fecha de Depósito')
                            ->default(now())
                            ->required(),
                    ])
                    ->action(function (array $data, Check $record) {
                        // 1. Buscamos la cuenta bancaria destino
                        $bankAccount = CompanyAccount::find($data['company_account_id']);

                        // 2. Creamos el movimiento de entrada en el banco
                        Transaction::create([
                            'company_account_id' => $bankAccount->id,
                            'type' => 'Income',
                            'amount' => $record->amount,
                            'description' => "Depósito Cheque N° {$record->number} ({$record->bank_name})",
                            'concept' => 'Depósito de Cheques',
                            'origin' => 'Fiscal', // Asumimos fiscal al entrar al banco
                            'payment_details' => ['check_id' => $record->id],
                        ]);

                        // 3. Sumamos la plata al saldo del banco
                        $bankAccount->increment('current_balance', $record->amount);

                        // 4. Marcamos el cheque como DEPOSITADO
                        $record->update([
                            'status' => 'Deposited',
                            // Podrías tener un campo 'deposited_in_account_id' en la tabla checks si quisieras rastrearlo mejor
                        ]);

                        Notification::make()->title('Cheque depositado correctamente')->success()->send();
                    }),
            ]);
    }
    
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListChecks::route('/'),
            
            // CORREGIDO: "CreateCheck" (Singular), no "CreateChecks"
            'create' => Pages\CreateCheck::route('/create'),
            
            'edit' => Pages\EditCheck::route('/{record}/edit'),
        ];
    }
}