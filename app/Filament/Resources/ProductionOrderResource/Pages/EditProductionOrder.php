<?php

namespace App\Filament\Resources\ProductionOrderResource\Pages;

use App\Filament\Resources\ProductionOrderResource;
use App\Models\ProductionOrder;
use App\Models\Sku;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\DB;
use Filament\Notifications\Notification;
use Filament\Forms;

class EditProductionOrder extends EditRecord
{
    protected static string $resource = ProductionOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // BOTÓN MAGICO EN LA PÁGINA DE EDICIÓN PARA FINALIZAR
            Actions\Action::make('finalizar_corte_edit')
                ->label('Finalizar e Ingresar Stock')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->visible(fn (ProductionOrder $record) => $record->status === 'en_proceso')
                ->modalWidth('7xl')
                ->closeModalByClickingAway(false)
                ->mountUsing(function (Forms\ComponentContainer $form, ProductionOrder $record) {
                    $form->fill([
                        'article_groups' => $record->article_groups,
                        'observations' => $record->observations,
                        'status' => 'draft', 
                    ]);
                })
                ->form(fn (ProductionOrder $record) => [
                    Forms\Components\Hidden::make('status'),
                    Forms\Components\ViewField::make('article_groups')
                        ->view('filament.components.production-matrix-editor')
                        ->viewData(['original_groups' => $record->article_groups]) // LA MATRIZ ORIGINAL P/ COMPARAR
                        ->columnSpanFull()
                        ->reactive(),
                    Forms\Components\Textarea::make('observations')
                        ->label('Observaciones / Mermas')
                        ->columnSpanFull(),
                ])
                ->action(function (ProductionOrder $record, array $data, Actions\Action $action) {
                    // VALIDACIÓN
                    $originalGroups = $record->article_groups ?? [];
                    foreach ($data['article_groups'] ?? [] as $gk => $groupData) {
                        foreach ($groupData['matrix'] ?? [] as $rk => $rowData) {
                            foreach ($rowData as $field => $qty) {
                                if (str_starts_with($field, 'qty_')) {
                                    $originalQty = $originalGroups[$gk]['matrix'][$rk][$field] ?? 0;
                                    if ((int)$originalQty > 0 && $qty === "") {
                                        Notification::make()->danger()->title('Faltan cantidades')->body("Dejaste una celda vacía. Poné '0' si no ingresó nada.")->persistent()->send();
                                        $action->halt();
                                    }
                                }
                            }
                        }
                    }

                    // GUARDADO Y SUMA AL STOCK
                    DB::transaction(function () use ($record, $data) {
                        $totalIngresado = 0;
                        foreach ($data['article_groups'] ?? [] as $groupKey => $groupData) {
                            $articleId = $groupData['article_id'];
                            foreach ($groupData['matrix'] ?? [] as $rowKey => $rowData) {
                                $colorId = $rowData['color_id'];
                                foreach ($rowData as $field => $qty) {
                                    if (str_starts_with($field, 'qty_') && $qty !== "" && (int)$qty > 0) {
                                        $sizeId = str_replace('qty_', '', $field);
                                        $cantidad = (int)$qty;
                                        
                                        $sku = Sku::where('article_id', $articleId)->where('color_id', $colorId)->where('size_id', $sizeId)->first();
                                        
                                        if ($sku) {
                                            $record->items()->create(['sku_id' => $sku->id, 'quantity' => $cantidad]);
                                            $sku->increment('stock_quantity', $cantidad);
                                            $totalIngresado += $cantidad;
                                        }
                                    }
                                }
                            }
                        }
                        $record->update([
                            'status' => 'finalizado', 
                            'article_groups' => $data['article_groups'], 
                            'observations' => $data['observations'] ?? null
                        ]);
                        Notification::make()->success()->title("Corte finalizado. Ingresaron {$totalIngresado} prendas.")->send();
                    });
                    
                    // RECARGAR PÁGINA PARA REFLEJAR CAMBIOS
                    $this->refreshFormData(['status', 'article_groups', 'observations']);
                }),

            Actions\DeleteAction::make()
                ->visible(fn (ProductionOrder $record) => $record->status === 'draft'),
        ];
    }
}