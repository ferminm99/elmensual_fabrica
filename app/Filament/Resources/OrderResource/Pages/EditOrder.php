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
        // CARGAR PEDIDOS HIJOS
        $children = \App\Models\Order::where('parent_id', $order->id)
            ->with(['items.sku.size', 'items.color'])
            ->get();

        $childGroupsData = [];
        foreach ($children as $child) {
            $items = $child->items->groupBy('article_id');
            $groups = [];
            foreach ($items as $articleId => $articleItems) {
                $matrix = [];
                foreach ($articleItems->groupBy('color_id') as $colorId => $colorItems) {
                    $first = $colorItems->first();
                    $row = [
                        'color_id' => $colorId,
                        'color_name' => $first->color?->name ?? 'S/N',
                        'color_hex' => $first->color?->hex_code ?? '#334155',
                    ];
                    foreach ($colorItems as $item) {
                        if($item->sku) $row["qty_{$item->sku->size_id}"] = $item->quantity;
                    }
                    $matrix[uniqid()] = $row;
                }
                $groups[] = ['article_id' => $articleId, 'matrix' => $matrix, 'child_id' => $child->id];
            }
            $childGroupsData[] = ['id' => $child->id, 'groups' => $groups];
        }
        $data['existing_children'] = $childGroupsData;

        return $data;
    }

    // app/Filament/Resources/OrderResource/Pages/EditOrder.php

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $childGroups = $this->data['child_groups'] ?? [];
        $articleGroups = $this->data['article_groups'] ?? [];

        return DB::transaction(function () use ($record, $data, $articleGroups, $childGroups) {
            $record->update($data);
            $currentStatus = $record->status instanceof \BackedEnum ? $record->status->value : $record->status;
            
            // 1. EL PADRE ES EDITABLE EN DRAFT Y STANDBY
            if (in_array($currentStatus, ['draft', 'standby', 'processing'])) {
                foreach ($articleGroups as $group) {
                    foreach ($group['matrix'] as $row) {
                        foreach ($row as $k => $val) {
                            if (str_starts_with($k, 'qty_')) {
                                $sizeId = str_replace('qty_', '', $k);
                                $sku = Sku::where('article_id', $group['article_id'])
                                    ->where('color_id', $row['color_id'])
                                    ->where('size_id', $sizeId)
                                    ->first();

                                if ($sku) {
                                    $record->items()->updateOrCreate(
                                        ['sku_id' => $sku->id],
                                        [
                                            'article_id' => $group['article_id'],
                                            'color_id' => $row['color_id'],
                                            'quantity' => (int)($val ?? 0),
                                            'unit_price' => $sku->article->base_cost,
                                            'subtotal' => (int)($val ?? 0) * $sku->article->base_cost
                                        ]
                                    );
                                }
                            }
                        }
                    }
                }
            }

            // 2. CREAR HIJO SI HAY DATOS EN LA MATRIZ DE ABAJO
            if ($currentStatus === 'standby' && !empty($childGroups)) {
                $hasItems = false;
                // Primero verificamos si realmente hay cantidades > 0 o < 0 (soporta negativos ahora)
                foreach ($childGroups as $cg) {
                    foreach ($cg['matrix'] as $r) {
                        foreach ($r as $k => $v) {
                            if (str_starts_with($k, 'qty_') && (int)$v != 0) { $hasItems = true; break 3; }
                        }
                    }
                }

                if ($hasItems) {
                    $child = Order::create([
                        'parent_id' => $record->id,
                        'client_id' => $record->client_id,
                        'status' => 'processing', 
                        'order_date' => now(),
                        'billing_type' => $record->billing_type,
                        'total_amount' => 0
                    ]);

                    $totalChild = 0;
                    foreach ($childGroups as $group) {
                        foreach ($group['matrix'] as $row) {
                            foreach ($row as $k => $qty) {
                                if (str_starts_with($k, 'qty_') && (int)$qty != 0) {
                                    $sizeId = str_replace('qty_', '', $k);
                                    $sku = Sku::where('article_id', $group['article_id'])->where('color_id', $row['color_id'])->where('size_id', $sizeId)->first();
                                    if ($sku) {
                                        $sub = (int)$qty * $sku->article->base_cost;
                                        $child->items()->create([
                                            'article_id' => $group['article_id'],
                                            'sku_id' => $sku->id,
                                            'color_id' => $row['color_id'],
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
                    $this->data['child_groups'] = []; // Limpiar formulario
                    Notification::make()->title("Pedido Hijo #{$child->id} creado.")->success()->send();
                }
            }
            return $record;
        });
    }

    // app/Filament/Resources/OrderResource/Pages/EditOrder.php

    protected function beforeFill(): void
    {
        $record = $this->getRecord();
        
        // Si el pedido está siendo armado por otro (que no sea el admin actual)
        if ($record->locked_at && $record->locked_by !== auth()->id()) {
            $diff = $record->locked_at->diffForHumans();
            $user = $record->lockedBy?->name ?? 'Un armador';
            
            Notification::make()
                ->warning()
                ->title("Pedido en uso")
                ->body("Este pedido está siendo armado por {$user} desde hace {$diff}. No deberías modificarlo ahora.")
                ->persistent()
                ->send();
        }
    }

    // Añadimos una validación extra antes de guardar
    protected function beforeSave(): void
    {
        if ($this->getRecord()->locked_at && $this->getRecord()->locked_by !== auth()->id()) {
            Notification::make()
                ->danger()
                ->title("Error al guardar")
                ->body("El pedido está bloqueado por un armador. Espera a que termine.")
                ->send();
                
            $this->halt(); // Detiene el proceso de guardado
        }
    }
}