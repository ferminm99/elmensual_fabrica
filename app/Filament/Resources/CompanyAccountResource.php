<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CompanyAccountResource\Pages;
use App\Models\CompanyAccount;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CompanyAccountResource extends Resource
{
    protected static ?string $model = CompanyAccount::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-library';
    protected static ?string $modelLabel = 'Cuenta / Caja';
    protected static ?string $pluralModelLabel = 'Cajas y Bancos';
    protected static ?string $navigationGroup = 'Tesorería'; // Agrupamos aquí

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Card::make()->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('Nombre de la Cuenta')
                        ->placeholder('Ej: Banco Galicia CC, Caja Chica')
                        ->required(),
                    
                    Forms\Components\Select::make('type')
                        ->label('Tipo')
                        ->options([
                            'bank' => 'Banco',
                            'cash' => 'Efectivo / Caja Física',
                            'wallet' => 'Billetera Virtual (MP, etc)',
                        ])
                        ->required(),

                    Forms\Components\TextInput::make('cbu')
                        ->label('CBU / CVU')
                        ->visible(fn (Forms\Get $get) => $get('type') !== 'cash'),

                    Forms\Components\TextInput::make('currency')
                        ->label('Moneda')
                        ->default('ARS')
                        ->disabled(), // Por ahora solo pesos para no complicar

                    Forms\Components\TextInput::make('current_balance')
                        ->label('Saldo Actual')
                        ->prefix('$')
                        ->numeric()
                        ->default(0)
                        ->helperText('Este saldo se actualiza solo con los cobros y pagos.'),
                ])->columns(2)
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Cuenta')
                    ->weight('bold')
                    ->icon(fn ($record) => match ($record->type) {
                        'bank' => 'heroicon-o-building-library',
                        'cash' => 'heroicon-o-banknotes',
                        'wallet' => 'heroicon-o-device-phone-mobile',
                        default => 'heroicon-o-credit-card',
                    }),

                Tables\Columns\TextColumn::make('cbu')
                    ->label('CBU')
                    ->copyable()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('current_balance')
                    ->label('SALDO DISPONIBLE')
                    ->money('ARS')
                    ->weight('black')
                    ->size(Tables\Columns\TextColumn\TextColumnSize::Large)
                    ->color(fn ($state) => $state >= 0 ? 'success' : 'danger'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCompanyAccounts::route('/'),
            'create' => Pages\CreateCompanyAccount::route('/create'),
            'edit' => Pages\EditCompanyAccount::route('/{record}/edit'),
        ];
    }
}