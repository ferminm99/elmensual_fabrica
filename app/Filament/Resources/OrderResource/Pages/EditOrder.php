<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use App\Models\Order;
use App\Enums\OrderStatus;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Barryvdh\DomPDF\Facade\Pdf;

class EditOrder extends EditRecord
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('change_status_modal')
                ->label('Cambiar Estado')
                ->icon('heroicon-m-arrow-path')
                ->color('warning')
                ->modalWidth('md')
                ->modalHeading('Actualizar Estado del Pedido')
                ->form([
                    Forms\Components\Select::make('new_status')->label('Nuevo Estado')->options(OrderStatus::class)->default(fn (Order $record) => $record->status)->required()->reactive(),
                    Forms\Components\Textarea::make('reason')->label('Motivo')->visible(fn(Forms\Get $get) => $get('new_status') && $get('new_status') !== $this->getRecord()->status->value && in_array($this->getRecord()->status->value, ['assembled','checked','dispatched']) && !in_array($get('new_status'), ['standby','cancelled']))->required(fn(Forms\Get $get) => $get('reason') !== null)
                ])
                ->action(function (Order $record, array $data) {
                    $record->update(['status' => $data['new_status']]);
                    Notification::make()->title('Estado actualizado correctamente')->success()->send();
                    $this->redirect($this->getResource()::getUrl('edit', ['record' => $record]));
                }),

            Actions\Action::make('print')->label('Imprimir')->icon('heroicon-o-printer')->color('success')
                ->action(fn() => response()->streamDownload(fn() => echo Pdf::loadView('pdf.picking-list', ['order' => $this->getRecord()])->output(), 'pedido-' . $this->getRecord()->id . '.pdf')),

            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $order = $this->getRecord();
        $grouped = $order->items->groupBy('article_id');
        $articleGroupsForm = [];
        foreach ($grouped as $articleId => $rows) {
            $variants = [];
            foreach ($rows as $row) {
                $variants[] = [
                    'color_id' => $row->color_id, 'sku_id' => $row->sku_id, 'size_id' => $row->sku?->size_id,
                    'quantity' => $row->quantity, 'packed_quantity' => $row->packed_quantity, 'unit_price' => $row->unit_price,
                ];
            }
            $articleGroupsForm[] = ['article_id' => $articleId, 'variants' => $variants];
        }
        $data['article_groups'] = $articleGroupsForm;
        return $data;
    }

    // --- MANEJO DE GUARDADO Y SPLIT ---
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        // Estados "Bloqueados" originales
        $wasLocked = in_array($record->status, [
            OrderStatus::Assembled, OrderStatus::Checked, OrderStatus::Dispatched, OrderStatus::Delivered, OrderStatus::Standby
        ]);

        // Verificamos si el usuario seleccionó Standby en el form
        $targetStatus = $data['status'] ?? $record->status;
        $isTargetStandby = ($targetStatus instanceof OrderStatus ? $targetStatus->value : $targetStatus) === OrderStatus::Standby->value;

        $articleGroups = $data['article_groups'] ?? [];
        unset($data['article_groups']);

        // VALIDACIÓN: Si estamos en modo Standby (o pasando a él), DEBE haber cambios
        if ($wasLocked && $isTargetStandby) {
            $hasDifferences = false;
            $existingItems = $record->items->keyBy(fn($item) => "{$item->article_id}_{$item->sku_id}_{$item->color_id}");
            
            foreach ($articleGroups as $group) {
                foreach ($group['variants'] ?? [] as $variant) {
                    $key = "{$group['article_id']}_{$variant['sku_id']}_{$variant['color_id']}";
                    $existing = $existingItems->get($key);
                    $newQty = intval($variant['quantity']);
                    
                    if (!$existing && $newQty > 0) { $hasDifferences = true; break 2; }
                    if ($existing && $newQty > $existing->quantity) { $hasDifferences = true; break 2; }
                }
            }

            if (!$hasDifferences) {
                Notification::make()
                    ->title('Acción Requerida')
                    ->body('Para pasar a STANDBY debes agregar al menos un artículo o aumentar una cantidad. No se detectaron diferencias.')
                    ->danger()
                    ->send();
                
                $this->halt(); // DETIENE TODO
            }
        }

        return DB::transaction(function () use ($record, $data, $articleGroups, $wasLocked) {
            $record->update($data); // Guardamos status y fecha

            if (!$wasLocked) {
                $record->items()->delete();
            }

            $existingItems = $record->items->keyBy(fn($item) => "{$item->article_id}_{$item->sku_id}_{$item->color_id}");
            $childItems = [];
            $totalAmount = 0;

            foreach ($articleGroups as $group) {
                $articleId = $group['article_id'];
                foreach ($group['variants'] ?? [] as $variant) {
                    $skuId = $variant['sku_id']; $colorId = $variant['color_id'];
                    $qtyForm = intval($variant['quantity']); $price = floatval($variant['unit_price']); $subtotal = $qtyForm * $price;

                    if ($wasLocked) {
                        $key = "{$articleId}_{$skuId}_{$colorId}";
                        $existingItem = $existingItems->get($key);

                        if ($existingItem) {
                            $qtyOriginal = $existingItem->quantity;
                            if ($qtyForm > $qtyOriginal) { // AUMENTO -> HIJO
                                $diff = $qtyForm - $qtyOriginal;
                                $childItems[] = ['article_id' => $articleId, 'sku_id' => $skuId, 'color_id' => $colorId, 'quantity' => $diff, 'unit_price' => $price, 'subtotal' => $diff * $price, 'packed_quantity' => 0];
                                $totalAmount += ($qtyOriginal * $price);
                            } else { // DISMINUCIÓN/IGUAL -> PADRE
                                $existingItem->update(['quantity' => $qtyForm, 'subtotal' => $subtotal]);
                                $totalAmount += $subtotal;
                            }
                        } else if ($qtyForm > 0) { // NUEVO -> HIJO
                            $childItems[] = ['article_id' => $articleId, 'sku_id' => $skuId, 'color_id' => $colorId, 'quantity' => $qtyForm, 'unit_price' => $price, 'subtotal' => $subtotal, 'packed_quantity' => 0];
                        }
                    } else if ($qtyForm > 0) { 
                        $record->items()->create(['article_id' => $articleId, 'sku_id' => $skuId, 'color_id' => $colorId, 'quantity' => $qtyForm, 'packed_quantity' => $variant['packed_quantity'] ?? 0, 'unit_price' => $price, 'subtotal' => $subtotal]);
                        $totalAmount += $subtotal;
                    }
                }
            }

            $record->update(['total_amount' => $totalAmount]);

            // GENERAR HIJO
            if ($wasLocked && count($childItems) > 0) {
                $childTotal = collect($childItems)->sum('subtotal');
                $childOrder = Order::create([
                    'client_id' => $record->client_id, 'parent_id' => $record->id, 'order_date' => now(),
                    'status' => OrderStatus::Processing, 'priority' => 3, 'billing_type' => $record->billing_type, 'total_amount' => $childTotal,
                ]);
                foreach ($childItems as $item) $childOrder->items()->create($item);

                if ($record->status !== OrderStatus::Cancelled && $record->status !== OrderStatus::Standby) {
                    $record->update(['status' => OrderStatus::Standby]);
                }

                Notification::make()->title('Pedido Dividido')->body("Se creó el Pedido Hijo #{$childOrder->id} y el padre pasó a STANDBY.")->warning()->persistent()->send();
            } else {
                Notification::make()->title('Guardado')->success()->send();
            }

            return $record;
        });
    }
}