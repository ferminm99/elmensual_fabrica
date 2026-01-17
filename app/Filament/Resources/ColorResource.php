<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ColorResource\Pages;
use App\Models\Color;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;

class ColorResource extends Resource
{
    protected static ?string $model = Color::class;
    protected static ?string $navigationIcon = 'heroicon-o-swatch';
    protected static ?string $navigationGroup = 'Configuración';
    protected static ?string $modelLabel = 'Color';
    protected static ?string $pluralModelLabel = 'Colores';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Nombre del Color')
                    ->required(),
                
                Forms\Components\ColorPicker::make('hex_code')
                    ->label('Selector Visual')
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\ColorColumn::make('hex_code')
                    ->label('Muestra'),

                Tables\Columns\TextColumn::make('skus_count')
                    ->counts('skus')
                    ->label('En Uso')
                    ->badge()
                    ->color(fn ($state) => $state > 0 ? 'warning' : 'gray'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),

                // PROTECCIÓN AL BORRAR
                Tables\Actions\DeleteAction::make()
                    ->before(function (Tables\Actions\DeleteAction $action, Color $record) {
                        if ($record->skus()->count() > 0) {
                            Notification::make()
                                ->danger()
                                ->title('Acción Bloqueada')
                                ->body('No puedes borrar este color porque hay productos usándolo.')
                                ->persistent()
                                ->send();
                            
                            $action->cancel();
                        }
                    }),
            ]);
    }
    
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListColors::route('/'),
        ];
    }
}