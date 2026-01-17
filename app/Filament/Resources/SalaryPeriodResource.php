<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SalaryPeriodResource\Pages;
use App\Models\SalaryPeriod;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SalaryPeriodResource extends Resource
{
    protected static ?string $model = SalaryPeriod::class;
    protected static ?string $navigationIcon = 'heroicon-o-calendar';
    
    protected static ?string $modelLabel = 'Período de Liquidación';
    protected static ?string $pluralModelLabel = 'Períodos';
    protected static ?string $navigationGroup = 'Recursos Humanos';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Grid::make(2)
                    ->schema([
                        Forms\Components\Select::make('month')
                            ->label('Mes')
                            ->options([
                                1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
                                5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
                                9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
                            ])
                            ->required(),

                        Forms\Components\TextInput::make('year')
                            ->label('Año')
                            ->numeric()
                            ->default(date('Y'))
                            ->required(),

                        Forms\Components\Select::make('status')
                            ->label('Estado')
                            ->options([
                                'Open' => 'Abierto',
                                'Closed' => 'Cerrado',
                                'Paid' => 'Pagado',
                            ])
                            ->default('Open')
                            ->required(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('month')
                    ->label('Mes')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        '1' => 'Enero', '2' => 'Febrero', '3' => 'Marzo', '4' => 'Abril',
                        '5' => 'Mayo', '6' => 'Junio', '7' => 'Julio', '8' => 'Agosto',
                        '9' => 'Septiembre', '10' => 'Octubre', '11' => 'Noviembre', '12' => 'Diciembre',
                        default => $state,
                    })
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('year')->label('Año'),
                
                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->colors([
                        'success' => 'Paid',
                        'warning' => 'Open',
                        'danger' => 'Closed',
                    ]),
            ])
            ->defaultSort('id', 'desc'); // Ver los más nuevos primero
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSalaryPeriods::route('/'),
            'create' => Pages\CreateSalaryPeriod::route('/create'),
            'edit' => Pages\EditSalaryPeriod::route('/{record}/edit'),
        ];
    }
}