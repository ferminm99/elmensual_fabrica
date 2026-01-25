<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use App\Models\Order;
use App\Models\OrderItem;
use App\Enums\OrderStatus;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Filament\Actions;
use Filament\Notifications\Notification;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Support\Exceptions\Halt; // Importante para detener el guardado

class EditOrder extends EditRecord
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
            
            Actions\Action::make('print')
                ->label('Imprimir Armado')
                ->icon('heroicon-o-printer')
                ->color('success')
                ->action(function () {
                    $record = $this->getRecord();
                    return response()->streamDownload(function () use ($record) {
                        echo Pdf::loadView('pdf.picking-list', ['order' => $record])->output();
                    }, 'pedido-' . $record->id . '.pdf');
                }),
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
                // Recuperamos size_id desde SKU para visualización
                $sizeId = $row->sku ? $row->sku->size_id : null;

                $variants[] = [
                    'color_id'        => $row->color_id,
                    'sku_id'          => $row->sku_id,
                    'size_id'         => $sizeId,
                    'quantity'        => $row->quantity,
                    'packed_quantity' => $row->packed_quantity,
                    'unit_price'      => $row->unit_price,
                ];
            }
            $articleGroupsForm[] = [
                'article_id' => $articleId,
                'variants'   => $variants,
            ];
        }

        $data['article_groups'] = $articleGroupsForm;
        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        // 1. VALIDACIÓN ANTI-TRAMPA
        // Verificamos si el estado final que intenta guardar es "Bloqueado"
        $lockedStatuses = [
            OrderStatus::Assembled->value, 
            OrderStatus::Checked->value, 
            OrderStatus::Dispatched->value, 
            OrderStatus::Delivered->value
        ];

        // El estado que viene del formulario
        $targetStatus = $data['status']; 
        if ($targetStatus instanceof OrderStatus) $targetStatus = $targetStatus->value;

        // Si intenta guardar en un estado bloqueado, verificamos si tocó las cantidades
        if (in_array($targetStatus, $lockedStatuses)) {
            $articleGroups = $data['article_groups'] ?? [];
            
            // Mapa de cantidades originales: Clave -> Cantidad
            $originalQuantities = $record->items->mapWithKeys(fn($item) => ["{$item->article_id}_{$item->sku_id}" => $item->quantity]);

            foreach ($articleGroups as $group) {
                foreach ($group['variants'] as $variant) {
                    $key = "{$group['article_id']}_{$variant['sku_id']}";
                    $newQty = intval($variant['quantity']);
                    $oldQty = $originalQuantities[$key] ?? 0;

                    if ($newQty !== $oldQty) {
                        Notification::make()
                            ->title('Acción Prohibida')
                            ->body("No puedes modificar las cantidades del pedido y guardarlo como '{$targetStatus}' al mismo tiempo. Para editar cantidades, primero guarda como 'Borrador' o 'Para Armar'.")
                            ->danger()
                            ->persistent()
                            ->send();
                        
                        throw new Halt(); // ESTO DETIENE EL GUARDADO AL INSTANTE
                    }
                }
            }
        }

        // --- SI PASA LA VALIDACIÓN, PROCESAMOS NORMALMENTE ---

        $articleGroups = $data['article_groups'] ?? [];
        unset($data['article_groups']);

        return DB::transaction(function () use ($record, $data, $articleGroups) {
            $originalItems = $record->items->mapWithKeys(function ($item) {
                $key = "{$item->article_id}_{$item->sku_id}"; 
                return [$key => $item->quantity];
            });

            $record->update($data);
            $record->items()->delete();

            $totalAmount = 0;
            $itemsForBackorder = [];

            foreach ($articleGroups as $group) {
                $articleId = $group['article_id'];
                $variants = $group['variants'] ?? [];

                foreach ($variants as $variant) {
                    $qtyNew = intval($variant['quantity']);
                    $price = floatval($variant['unit_price']);
                    $subtotal = $qtyNew * $price;

                    $record->items()->create([
                        'article_id'      => $articleId,
                        'sku_id'          => $variant['sku_id'],
                        'color_id'        => $variant['color_id'],
                        'quantity'        => $qtyNew,
                        'packed_quantity' => $variant['packed_quantity'] ?? 0,
                        'unit_price'      => $price,
                        'subtotal'        => $subtotal,
                    ]);

                    $totalAmount += $subtotal;

                    // Backorder logic
                    $key = "{$articleId}_{$variant['sku_id']}";
                    if ($originalItems->has($key)) {
                        $qtyOriginal = $originalItems[$key];
                        if ($qtyOriginal > $qtyNew) {
                            $diff = $qtyOriginal - $qtyNew;
                            $itemsForBackorder[] = [
                                'article_id' => $articleId,
                                'sku_id'     => $variant['sku_id'],
                                'color_id'   => $variant['color_id'],
                                'quantity'   => $diff,
                                'unit_price' => $price,
                            ];
                        }
                    }
                }
            }

            $record->update(['total_amount' => $totalAmount]);

            if (count($itemsForBackorder) > 0) {
                $childOrder = Order::create([
                    'client_id'    => $record->client_id,
                    'parent_id'    => $record->id,
                    'order_date'   => now(),
                    'status'       => OrderStatus::Processing,
                    'billing_type' => $record->billing_type,
                    'total_amount' => 0, 
                ]);

                $childTotal = 0;
                foreach ($itemsForBackorder as $item) {
                    $sub = $item['quantity'] * $item['unit_price'];
                    $childOrder->items()->create([
                        'article_id' => $item['article_id'],
                        'sku_id'     => $item['sku_id'],
                        'color_id'   => $item['color_id'],
                        'quantity'   => $item['quantity'],
                        'packed_quantity' => 0, 
                        'unit_price' => $item['unit_price'],
                        'subtotal'   => $sub,
                    ]);
                    $childTotal += $sub;
                }
                $childOrder->update(['total_amount' => $childTotal]);

                Notification::make()
                    ->title('Pedido Dividido')
                    ->body("Se generó el Pedido #{$childOrder->id} con los faltantes.")
                    ->success()
                    ->send();
            }

            return $record;
        });
    }
}