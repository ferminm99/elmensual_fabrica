<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SizeResource\Pages;
use App\Models\Size;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;

class SizeResource extends Resource
{
    protected static ?string $model = Size::class;
    protected static ?string $navigationIcon = 'heroicon-o-arrows-up-down'; 
    protected static ?string $navigationGroup = 'Configuración'; 
    protected static ?string $modelLabel = 'Talle';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Nombre del Talle')
                    ->required()
                    ->unique(ignoreRecord: true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Talle')
                    ->sortable()
                    ->searchable(),
                
                // Muestra en cuántos productos se usa este talle
                Tables\Columns\TextColumn::make('skus_count')
                    ->counts('skus')
                    ->label('Productos Usándolo')
                    ->badge()
                    ->color(fn ($state) => $state > 0 ? 'warning' : 'gray'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                
                // EL GUARDIÁN DE SEGURIDAD
                Tables\Actions\DeleteAction::make()
                    ->before(function (Tables\Actions\DeleteAction $action, Size $record) {
                        if ($record->skus()->count() > 0) {
                            Notification::make()
                                ->danger()
                                ->title('No se puede borrar')
                                ->body('Este talle tiene stock asignado. Borra primero las variantes del producto.')
                                ->persistent()
                                ->send();
                            
                            $action->cancel(); // ¡Aquí frenamos el borrado!
                        }
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSizes::route('/'),
        ];
    }
}