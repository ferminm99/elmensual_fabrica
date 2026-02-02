<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use App\Models\Order;
use App\Models\Sku;
use App\Enums\OrderStatus;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Filament\Notifications\Notification;

class EditOrder extends EditRecord
{
    protected static string $resource = OrderResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $order = $this->getRecord();
        $items = $order->items()->with(['sku.size', 'color'])->get();
        $grouped = $items->groupBy('article_id');
        $groups = [];

        foreach ($grouped as $articleId => $articleItems) {
            $matrix = [];
            $groupedColor = $articleItems->groupBy('color_id');
            foreach ($groupedColor as $colorId => $colorItems) {
                $first = $colorItems->first();
                $row = [
                    'color_id' => $colorId,
                    'color_name' => $first->color?->name ?? 'S/N',
                    'color_hex' => $first->color?->hex_code ?? '#334155',
                ];
                foreach ($colorItems as $item) {
                    if($item->sku) {
                        $row["qty_{$item->sku->size_id}"] = $item->quantity;
                        $row["packed_{$item->sku->size_id}"] = $item->packed_quantity ?? 0;
                    }
                }
                $matrix[uniqid()] = $row;
            }
            $groups[uniqid()] = ['article_id' => $articleId, 'matrix' => $matrix];
        }
        $data['article_groups'] = $groups;
        $data['child_groups'] = []; 
        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $childGroups = $data['child_groups'] ?? [];
        $articleGroups = $data['article_groups'] ?? [];
        unset($data['child_groups'], $data['article_groups']);

        return DB::transaction(function () use ($record, $data, $articleGroups, $childGroups) {
            $record->update($data);

            // Si es Draft o Processing, guardamos en el registro actual
            if (in_array($record->status->value, ['draft', 'processing'])) {
                foreach ($articleGroups as $group) {
                    foreach ($group['matrix'] as $row) {
                        foreach ($row as $k => $val) {
                            if (str_starts_with($k, 'qty_') || str_starts_with($k, 'packed_')) {
                                $sizeId = str_replace(['qty_', 'packed_'], '', $k);
                                $sku = Sku::where('article_id', $group['article_id'])->where('color_id', $row['color_id'])->where('size_id', $sizeId)->first();
                                
                                if ($sku) {
                                    $field = str_starts_with($k, 'qty_') ? 'quantity' : 'packed_quantity';
                                    // EL FIX: Asegurar entero, nunca null o string vacío
                                    $valClean = (int)($val ?? 0);

                                    $record->items()->updateOrCreate(
                                        ['sku_id' => $sku->id],
                                        [
                                            'article_id' => $group['article_id'],
                                            'color_id' => $row['color_id'],
                                            $field => $valClean,
                                            'unit_price' => $sku->article->base_cost,
                                            'subtotal' => (int)($row["qty_{$sizeId}"] ?? 0) * $sku->article->base_cost
                                        ]
                                    );
                                }
                            }
                        }
                    }
                }
            }
            // 2. FÁBRICA DE HIJOS (Crea nuevo pedido si hay carga adicional en Standby)
            if ($record->status->value === 'standby' && count($childGroups) > 0) {
                $child = Order::create([
                    'parent_id' => $record->id,
                    'client_id' => $record->client_id,
                    'status' => OrderStatus::Processing,
                    'order_date' => now(),
                    'billing_type' => $record->billing_type,
                    'total_amount' => 0
                ]);

                $totalChild = 0;
                foreach ($childGroups as $group) {
                    $cArticleId = $group['article_id'];
                    foreach ($group['matrix'] as $row) {
                        $cColorId = $row['color_id'];
                        foreach ($row as $k => $qty) {
                            if (str_starts_with($k, 'qty_') && (int)$qty > 0) {
                                $sizeId = str_replace('qty_', '', $k);
                                $sku = Sku::where('article_id', $cArticleId)
                                          ->where('color_id', $cColorId)
                                          ->where('size_id', $sizeId)
                                          ->first();
                                if ($sku) {
                                    $sub = (int)$qty * $sku->article->base_cost;
                                    $child->items()->create([
                                        'article_id' => $cArticleId,
                                        'sku_id' => $sku->id,
                                        'color_id' => $cColorId,
                                        'quantity' => (int)$qty,
                                        'unit_price' => $sku->article->base_cost,
                                        'subtotal' => $sub
                                    ]);
                                    $totalChild += $sub;
                                }
                            }
                        }
                    }
                }
                $child->update(['total_amount' => $totalChild]);
                Notification::make()->title("Orden de Trabajo #{$child->id} generada.")->success()->send();
            }

            return $record;
        });
    }
}