<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RoleResource\Pages;
use Spatie\Permission\Models\Role;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class RoleResource extends Resource
{
    protected static ?string $model = Role::class;

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';
    
    // Español
    protected static ?string $modelLabel = 'Rol';
    protected static ?string $pluralModelLabel = 'Roles y Permisos';
    protected static ?string $navigationGroup = 'Configuración';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Nombre del Rol')
                    ->helperText('Ej: Vendedor, Tesorería, Gerencia')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),

                // Aquí podrías agregar un Select múltiple para Permisos si los tuvieras creados
                // Por ahora lo dejaremos simple solo con el nombre
                Forms\Components\Select::make('guard_name')
                    ->label('Guardia')
                    ->options(['web' => 'Web'])
                    ->default('web')
                    ->disabled()
                    ->dehydrated(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Rol')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Super Admin' => 'danger',
                        'Gerencia' => 'warning',
                        'Vendedor' => 'success',
                        default => 'gray',
                    }),
                
                Tables\Columns\TextColumn::make('users_count')
                    ->counts('users')
                    ->label('Usuarios Asignados'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRoles::route('/'),
            'create' => Pages\CreateRole::route('/create'),
            'edit' => Pages\EditRole::route('/{record}/edit'),
        ];
    }
}