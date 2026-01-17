<?php

namespace App\Filament\Resources\ArticleResource\Pages;

use App\Filament\Resources\ArticleResource;
use App\Models\Color;
use App\Models\Size;
use App\Models\Sku;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditArticle extends EditRecord
{
    protected static string $resource = ArticleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Botón estándar de borrar
            Actions\DeleteAction::make(),

            // --- NUEVO BOTÓN MAGICO: GENERADOR DE VARIANTES ---
            Actions\Action::make('generateVariants')
                ->label('Generar Combinaciones')
                ->icon('heroicon-o-squares-plus')
                ->color('primary')
                ->form([
                    Forms\Components\Section::make('Selecciona los grupos')
                        ->description('El sistema creará todas las combinaciones posibles entre los talles y colores seleccionados.')
                        ->schema([
                            // SELECTOR MÚLTIPLE DE TALLES
                            Forms\Components\Select::make('size_ids')
                                ->label('Talles a Generar')
                                ->options(Size::all()->pluck('name', 'id'))
                                ->multiple() // ¡Permite seleccionar varios!
                                ->searchable()
                                ->required(),

                            // SELECTOR MÚLTIPLE DE COLORES
                            Forms\Components\Select::make('color_ids')
                                ->label('Colores a Generar')
                                ->options(Color::all()->pluck('name', 'id'))
                                ->multiple() // ¡Permite seleccionar varios!
                                ->searchable()
                                ->required(),

                            Forms\Components\TextInput::make('initial_stock')
                                ->label('Stock Inicial para todos')
                                ->numeric()
                                ->default(0)
                                ->required(),
                        ])
                ])
                ->action(function (array $data, $record) {
                    // $record es el Artículo actual (La bombacha)
                    $sizes = $data['size_ids'];
                    $colors = $data['color_ids'];
                    $stock = $data['initial_stock'];
                    $count = 0;

                    // BUCLE DOBLE: Recorremos Talles y Colores
                    foreach ($sizes as $sizeId) {
                        foreach ($colors as $colorId) {
                            
                            // Usamos firstOrCreate para no duplicar si ya existe esa combinación
                            $sku = Sku::firstOrCreate(
                                [
                                    'article_id' => $record->id,
                                    'size_id' => $sizeId,
                                    'color_id' => $colorId,
                                ],
                                [
                                    'stock_quantity' => $stock,
                                    'min_stock' => 2,
                                    'code' => uniqid(), // Código temporal
                                ]
                            );
                            
                            if ($sku->wasRecentlyCreated) {
                                $count++;
                            }
                        }
                    }

                    Notification::make()
                        ->title("Se generaron {$count} variantes nuevas")
                        ->success()
                        ->send();
                    
                    // Recargamos la página para que aparezcan en el Repeater de abajo
                    $this->redirect($this->getResource()::getUrl('edit', ['record' => $record])); 
                }),
        ];
    }
}