<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ClientResource\Pages;
use App\Filament\Resources\ClientResource\RelationManagers\PaymentsRelationManager;
use App\Filament\Resources\ClientResource\RelationManagers\OrdersRelationManager;
use App\Filament\Resources\ProductionOrderResource\RelationManagers\ActivitiesRelationManager;
use App\Models\Client;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Set;
use App\Models\Bank;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ClientResource extends Resource
{
    protected static ?string $model = Client::class;
    protected static ?string $navigationIcon = 'heroicon-o-user-group';
    protected static ?string $modelLabel = 'Cliente';
    protected static ?string $pluralModelLabel = 'Clientes';
    protected static ?string $navigationGroup = 'Ventas';

    // MAGIA DE FILAMENT: Esto hace que al editar/ver puedas consultar clientes dados de baja
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Información del Cliente')
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
                        Forms\Components\TextInput::make('phone')
                            ->label('Teléfono')
                            ->tel()
                            ->maxLength(255),
                        Forms\Components\Select::make('afip_tax_condition_id')
                            ->label('Condición frente al IVA')
                            ->options(Client::AFIP_TAX_CONDITIONS)
                            ->default(5)
                            ->required(),  
                        Forms\Components\TextInput::make('default_discount')
                            ->label('Descuento Fijo (%)')
                            ->numeric()
                            ->default(0)
                            ->suffix('%'),
                        Forms\Components\Select::make('salesman_id')
                            ->label('Viajante Asignado')
                            ->relationship('salesman', 'name')
                            ->searchable()
                            ->preload(),
                        // CAMPO OBSERVACIONES
                        Forms\Components\Textarea::make('observations')
                            ->label('Observaciones')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])->columns(2),

                Forms\Components\Section::make('Ubicación y Logística')
                    ->schema([
                        Forms\Components\Select::make('zone_id')
                            ->label('Zona')
                            ->options(\App\Models\Zone::all()->pluck('name', 'id'))
                            ->searchable()
                            ->live()
                            ->dehydrated(false)
                            ->afterStateUpdated(fn (Forms\Set $set) => $set('locality_id', null))
                            ->afterStateHydrated(function ($set, $record) {
                                if ($record?->locality) {
                                    $set('zone_id', $record->locality->zone_id);
                                }
                            }),
                        Forms\Components\Select::make('locality_id')
                            ->label('Localidad')
                            ->options(function (Forms\Get $get) {
                                $zoneId = $get('zone_id');
                                return $zoneId 
                                    ? \App\Models\Locality::where('zone_id', $zoneId)->pluck('name', 'id')
                                    : \App\Models\Locality::all()->pluck('name', 'id');
                            })
                            ->searchable()
                            ->required()
                            ->live(),
                        Forms\Components\TextInput::make('address')
                            ->label('Dirección')
                            ->maxLength(255),
                        // CAMPO CÓDIGO POSTAL
                        Forms\Components\TextInput::make('postal_code')
                            ->label('Código Postal (C.P.)')
                            ->maxLength(20),
                    ])->columns(2),

                
                Forms\Components\Section::make('Cuentas Bancarias del Cliente')
                    ->description('Cargá los CBU/CVU desde donde este cliente nos suele transferir o enviar cheques.')
                    ->collapsed() // Lo dejamos cerradito para que no moleste si no lo usás
                    ->schema([
                        Forms\Components\Repeater::make('bankAccounts')
                            ->relationship() // Engancha con la función que pusimos en Client.php
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
                                            
                                            if ($bank) {
                                                $set('bank_id', $bank->id);
                                            } else {
                                                $billetera = \App\Models\Bank::where('code', '000')->first();
                                                if ($billetera) {
                                                    $set('bank_id', $billetera->id);
                                                }
                                            }
                                        }
                                    }),
                                    
                                Forms\Components\Select::make('bank_id')
                                    ->label('Banco / Billetera')
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
                Forms\Components\Section::make('Cuentas Corrientes')
                    ->schema([
                        Forms\Components\TextInput::make('fiscal_debt')->label('Saldo Fiscal')->prefix('$')->numeric()->default(0),
                        Forms\Components\TextInput::make('internal_debt')->label('Saldo Interno')->prefix('$')->numeric()->default(0),
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
                    ->description(fn (Client $record) => 
                        ($record->locality?->name ?? 'S/L') . ' - ' . ($record->locality?->zone?->name ?? 'S/Z')
                    ),
                Tables\Columns\TextColumn::make('postal_code')
                    ->label('C.P.')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true), // Lo ocultamos por defecto para no saturar
                Tables\Columns\TextColumn::make('fiscal_debt') 
                    ->label('Saldo Oficial')
                    ->money('ARS')
                    ->sortable(),
                Tables\Columns\TextColumn::make('internal_debt')
                    ->label('Saldo Interno')
                    ->money('ARS')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('real_total')
                    ->label('TOTAL REAL')
                    ->money('ARS')
                    ->state(fn (Client $record) => $record->fiscal_debt + $record->internal_debt)
                    ->weight('black')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                // ESTE FILTRO TE PERMITE VER A LOS DADOS DE BAJA
                Tables\Filters\TrashedFilter::make()->label('Estado de Baja'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                // EL BOTÓN MÁGICO DE BAJA
                Tables\Actions\DeleteAction::make()
                    ->label('Dar de Baja')
                    ->modalHeading('¿Dar de baja al cliente?')
                    ->modalDescription('El cliente desaparecerá de las búsquedas, pero sus pedidos se mantendrán intactos en el historial.')
                    ->modalSubmitActionLabel('Sí, dar de baja'),
                // EL BOTÓN PARA REVIVIRLOS (Solo aparece si están dados de baja)
                Tables\Actions\RestoreAction::make()
                    ->label('Dar de Alta'),
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