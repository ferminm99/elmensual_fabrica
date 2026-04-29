<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CheckResource\Pages;
use App\Models\Check;
use App\Models\CompanyAccount;
use App\Models\Transaction;
use App\Models\Bank;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
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
                Forms\Components\Section::make('Tipo y Contabilidad')
                    ->columns(3)
                    ->schema([
                        Forms\Components\Select::make('type')
                            ->label('Origen del Cheque')
                            ->options([
                                'ThirdParty' => 'De un Cliente (Tercero)',
                                'Own' => 'Propio (Nuestra Firma)',
                            ])
                            ->default('ThirdParty')
                            ->required()
                            ->live(), // Para mostrar/ocultar el selector de cliente

                        Forms\Components\Select::make('origin')
                            ->label('Tipo de Venta (B/N)')
                            ->options([
                                'Fiscal' => 'Blanco (Fiscal)',
                                'Internal' => 'Negro (Interno)',
                            ])
                            ->default('Fiscal')
                            ->required(),

                        Forms\Components\Toggle::make('is_echeq')
                            ->label('¿Es E-Cheq (Digital)?')
                            ->inline(false)
                            ->onIcon('heroicon-m-device-phone-mobile')
                            ->offIcon('heroicon-m-document-text'),
                    ]),

                Forms\Components\Section::make('Datos del Cheque')
                    ->columns(2)
                    ->schema([
                        Forms\Components\Select::make('bank_id')
                            ->label('Banco Emisor')
                            ->relationship('bank', 'name')
                            ->searchable()
                            ->preload() // Carga los bancos al abrir para que busque al instante
                            ->required(),

                        Forms\Components\TextInput::make('number')
                            ->label('Número de Cheque')
                            ->required(),

                        Forms\Components\TextInput::make('owner')
                            ->label('Firmante / Dueño del Cheque')
                            ->placeholder('Nombre o CUIT que figura en el cheque'),

                        Forms\Components\DatePicker::make('payment_date')
                            ->label('Fecha de Cobro')
                            ->required(),

                        Forms\Components\TextInput::make('amount')
                            ->label('Monto')
                            ->prefix('$')
                            ->numeric()
                            ->required(),

                        Forms\Components\Select::make('status')
                            ->label('Estado Inicial')
                            ->options([
                                'InPortfolio' => 'En Cartera',
                                'Deposited' => 'Depositado',
                                'Delivered' => 'Entregado',
                                'Rejected' => 'Rechazado',
                            ])
                            ->default('InPortfolio')
                            ->required()
                            ->live(), // Para mostrar/ocultar el selector de proveedor
                    ]),

                Forms\Components\Section::make('Vinculación')
                    ->schema([
                        // Solo se muestra si el cheque es de un Cliente
                        Forms\Components\Select::make('client_id')
                            ->relationship('client', 'name')
                            ->label('Cliente que nos dio el cheque')
                            ->searchable()
                            ->required(fn (Get $get) => $get('type') === 'ThirdParty')
                            ->visible(fn (Get $get) => $get('type') === 'ThirdParty'),

                        // Solo se muestra si lo estamos cargando como "Ya entregado"
                        Forms\Components\Select::make('supplier_id')
                            ->relationship('supplier', 'name')
                            ->label('Proveedor al que se le entregó')
                            ->searchable()
                            ->required(fn (Get $get) => $get('status') === 'Delivered')
                            ->visible(fn (Get $get) => $get('status') === 'Delivered'),
                    ])->columns(2),
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
                
                Tables\Columns\TextColumn::make('bank.name')
                    ->label('Banco')
                    ->searchable()
                    ->description(fn (Check $record): string => $record->owner ? 'Firma: ' . $record->owner : ''),
                
                Tables\Columns\TextColumn::make('amount')
                    ->label('Monto')
                    ->money('ARS')
                    ->weight('bold'),
                
                Tables\Columns\TextColumn::make('payment_date')
                    ->label('Fecha Cobro')
                    ->date('d/m/Y')
                    ->sortable()
                    ->color(fn ($state) => $state <= now() ? 'danger' : 'gray'),
                
                // --- NUEVO: Blanco / Negro ---
                Tables\Columns\TextColumn::make('origin')
                    ->label('Tipo (B/N)')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Fiscal' => 'success',
                        'Internal' => 'gray', // O el color que prefieras para "Negro"
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'Fiscal' => 'Blanco',
                        'Internal' => 'Negro',
                        default => $state,
                    }),

                // --- NUEVO: Físico o Echeq ---
                Tables\Columns\IconColumn::make('is_echeq')
                    ->label('Formato')
                    ->boolean()
                    ->trueIcon('heroicon-o-device-phone-mobile')
                    ->falseIcon('heroicon-o-document')
                    ->trueColor('primary')
                    ->falseColor('gray')
                    ->tooltip(fn ($state) => $state ? 'E-Cheq (Digital)' : 'Cheque Físico'),

                // --- DE QUIÉN VINO Y A QUIÉN FUE ---
                Tables\Columns\TextColumn::make('client.name')
                    ->label('Origen / Destino')
                    ->searchable()
                    ->formatStateUsing(function (string $state, Check $record) {
                        // Si es de un cliente (Tercero)
                        if ($record->type === 'ThirdParty') {
                            $origen = "De: " . ($record->client ? $record->client->name : 'Desconocido');
                        } else {
                            $origen = "Cheque Propio";
                        }
                        
                        // Si ya lo entregamos a un proveedor, lo mostramos abajo
                        if (($record->status->value ?? $record->status) === 'Used' && $record->supplier) {
                            return new HtmlString($origen . '<br><span class="text-xs text-orange-600 font-bold">Entregado a: ' . $record->supplier->name . '</span>');
                        }
                        
                        return $origen;
                    }),

                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->color(fn ($state) => match ($state->value ?? $state) {
                        'InPortfolio' => 'info',      
                        'Deposited' => 'success',     
                        'Delivered' => 'warning',          
                        'Rejected' => 'danger',       
                        default => 'gray',
                    })
                    ->formatStateUsing(fn ($state) => match ($state->value ?? $state) {
                        'InPortfolio' => 'En Cartera',
                        'Deposited' => 'Depositado',
                        'Delivered' => 'Entregado',
                        'Rejected' => 'Rechazado',
                        default => $state,
                    }),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'InPortfolio' => 'En Cartera',
                        'Deposited' => 'Depositados',
                        'Delivered' => 'Entregados',
                        'Rejected' => 'Rechazados',
                    ])
                    ->default('InPortfolio'),
                
                // --- NUEVOS FILTROS MÚLTIPLES ---
                Tables\Filters\SelectFilter::make('origin')
                    ->label('Contabilidad')
                    ->options([
                        'Fiscal' => 'Solo Blanco',
                        'Internal' => 'Solo Negro',
                    ]),
                
                Tables\Filters\TernaryFilter::make('is_echeq')
                    ->label('Físico o Digital')
                    ->placeholder('Todos')
                    ->trueLabel('Solo Echeqs')
                    ->falseLabel('Solo Físicos'),
            ])
            ->actions([
                Tables\Actions\Action::make('deposit')
                    ->label('Depositar')
                    ->icon('heroicon-o-arrow-right-circle')
                    ->color('success')
                    ->visible(fn (Check $record) => ($record->status->value ?? $record->status) === 'InPortfolio')
                    ->form([
                        Forms\Components\Select::make('company_account_id')
                            ->label('¿A qué Banco lo depositas?')
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

                        Transaction::create([
                            'company_account_id' => $bankAccount->id,
                            'type' => 'Income',
                            'amount' => $record->amount,
                            'description' => "Depósito Cheque N° {$record->number} ({$record->bank_name})",
                            'concept' => 'Depósito Cheque',
                            'origin' => $record->origin, // Respeta si el cheque era blanco o negro
                            'payment_details' => ['check_id' => $record->id],
                        ]);

                        $bankAccount->increment('current_balance', $record->amount);

                        $record->update([
                            'status' => \App\Enums\CheckStatus::DEPOSITED,
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