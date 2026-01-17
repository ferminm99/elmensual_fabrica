<?php

namespace App\Filament\Resources\ArticleResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use App\Models\Color;
use App\Models\Size;

class SkusRelationManager extends RelationManager
{
    protected static string $relationship = 'skus';
    protected static ?string $title = 'Control de Stock por Variantes';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                // Esto es para cuando creas UNO manual
                Forms\Components\Select::make('size_id')->relationship('size', 'name')->required(),
                Forms\Components\Select::make('color_id')->relationship('color', 'name')->required(),
                Forms\Components\TextInput::make('stock_quantity')->numeric()->required(),
                Forms\Components\TextInput::make('code')->default(fn()=>uniqid()),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->groups(['size.name'])
            ->defaultGroup('size.name')
            ->recordTitleAttribute('code')
            ->columns([
                // TALLE
                Tables\Columns\TextColumn::make('size.name')
                    ->label('Talle')
                    ->sortable()
                    ->width('1%')
                    ->weight('bold')
                    ->alignCenter(),

                // COLOR (Aquí está la protección)
                Tables\Columns\TextColumn::make('color.name')
                    ->label('Color')
                    ->sortable()
                    ->width('1%')
                    ->formatStateUsing(function ($state, $record) {
                        // 1. INTENTO LEER EL COLOR CON PROTECCIÓN (?->)
                        // Si la relación está rota, $hex será null en vez de error.
                        $hex = $record->color?->hex_code;

                        // 2. DETECTOR DE ZOMBIES
                        // Si no hay hex, es una variante vieja/rota.
                        if (empty($hex)) {
                            return '<div style="color: gray; font-size: 0.8em; font-weight: bold; border: 1px dashed #ccc; padding: 2px 8px; border-radius: 4px; white-space: nowrap;">
                                ⚠️ Roto / Sin Color
                            </div>';
                        }

                        // 3. ASEGURAR #
                        if (!str_starts_with($hex, '#')) {
                            $hex = '#' . $hex;
                        }

                        // 4. CONTRASTE AUTOMÁTICO
                        $r = hexdec(substr($hex, 1, 2));
                        $g = hexdec(substr($hex, 3, 2));
                        $b = hexdec(substr($hex, 5, 2));
                        $yiq = (($r * 299) + ($g * 587) + ($b * 114)) / 1000;
                        $textColor = ($yiq >= 128) ? '#000000' : '#FFFFFF';

                        // 5. DIBUJAR LA PASTILLA
                        return sprintf(
                            '<div style="background-color: %s; color: %s; padding: 4px 12px; border-radius: 6px; font-weight: bold; white-space: nowrap; border: 1px solid rgba(0,0,0,0.1); box-shadow: 0 1px 2px rgba(0,0,0,0.1);">
                                %s
                            </div>',
                            $hex,
                            $textColor,
                            $state ?? 'S/N'
                        );
                    })
                    ->html(), // IMPORTANTE: Habilita el HTML

                // STOCK
                Tables\Columns\TextInputColumn::make('stock_quantity')
                    ->label('Stock')
                    ->type('number')
                    ->rules(['numeric', 'min:0'])
                    ->width('100px')
                    ->alignCenter(),

                // SKU
                Tables\Columns\TextColumn::make('code')
                    ->label('SKU')
                    ->color('gray')
                    ->size('xs')
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()->label('Agregar Variante'),
            ])
            ->actions([
                Tables\Actions\DeleteAction::make()->label(''),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }
}