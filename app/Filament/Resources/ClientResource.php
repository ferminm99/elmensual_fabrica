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

                        // --- TU SISTEMA DE DESCUENTOS ---
                        Forms\Components\TextInput::make('default_discount')
                            ->label('Descuento Fijo (%)')
                            ->numeric()
                            ->default(0)
                            ->suffix('%'),

                        // --- SISTEMA DE REFERIDOS ---
                        Forms\Components\Select::make('referred_by_id')
                            ->label('Viajante Asignado')
                            ->relationship('salesman', 'name') // Usa la relación definida en el paso anterior
                            ->searchable()
                            ->preload()
                            ->placeholder('Seleccione un viajante'),
                            
                    ])->columns(2),

                Forms\Components\Section::make('Ubicación y Logística')
                    ->schema([
                        // Selector de Zona (No se guarda en el cliente, sirve para filtrar)
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

                        // Selector de Localidad (Este sí se guarda en la DB)
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
                            ->maxLength(255)
                            ->columnSpanFull(),
                    ])->columns(2),

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

                Tables\Columns\TextColumn::make('fiscal_debt') 
                    ->label('Saldo Oficial')
                    ->money('ARS')
                    ->sortable(),

                // SE OCULTA POR DEFECTO PARA PRIVACIDAD
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
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->before(function (Tables\Actions\DeleteAction $action, Client $record) {
                        if ($record->orders()->exists()) {
                            Notification::make()
                                ->title('No se puede borrar')
                                ->body('El cliente tiene pedidos asociados. Deberías darlo de baja en su lugar.')
                                ->danger()
                                ->send();
                            $action->cancel();
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