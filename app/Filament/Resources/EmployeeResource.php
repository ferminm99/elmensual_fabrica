<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EmployeeResource\Pages;
use App\Models\Employee;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class EmployeeResource extends Resource
{
    protected static ?string $model = Employee::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    // Configuración Español
    protected static ?string $modelLabel = 'Empleado';
    protected static ?string $pluralModelLabel = 'Empleados';
    protected static ?string $navigationGroup = 'Recursos Humanos';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Datos Personales')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nombre Completo')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('cuil')
                            ->label('CUIL')
                            ->required()
                            ->maxLength(20)
                            ->unique(ignoreRecord: true), // No permitir 2 empleados con mismo CUIL
                    ])->columns(2),

                Forms\Components\Section::make('Datos Bancarios y Salariales')
                    ->schema([
                        Forms\Components\TextInput::make('cbu')
                            ->label('CBU / Alias')
                            ->helperText('Para transferencias de sueldo')
                            ->maxLength(255),

                        Forms\Components\TextInput::make('salary_base')
                            ->label('Sueldo Básico')
                            ->numeric()
                            ->prefix('$')
                            ->required(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Empleado')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('cuil')
                    ->label('CUIL')
                    ->searchable(),

                Tables\Columns\TextColumn::make('cbu')
                    ->label('CBU')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('salary_base')
                    ->label('Sueldo Base')
                    ->money('ARS')
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEmployees::route('/'),
            'create' => Pages\CreateEmployee::route('/create'),
            'edit' => Pages\EditEmployee::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        // Solo RRHH y Admins ven esto
        return auth()->user()->hasAnyRole(['Admin', 'RRHH']);
    }
}