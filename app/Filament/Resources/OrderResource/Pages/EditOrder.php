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

    protected function beforeFill(): void
    {
        $record = $this->getRecord();
        $userId = auth()->id();
        $ahora = now();

        // Consideramos un bloqueo "vencido" si pasaron más de 2 minutos
        $isLocked = $record->locked_at && $record->locked_at->diffInMinutes($ahora) < 2;

        if ($isLocked && $record->locked_by !== $userId) {
            $user = $record->lockedBy?->name ?? 'Un armador';
            Notification::make()
                ->danger()
                ->title("PEDIDO OCUPADO")
                ->body("{$user} lo tiene abierto. Intenta de nuevo en un momento.")
                ->persistent()
                ->send();
                
            // Redirigir afuera si querés bloqueo estricto
            // $this->redirect($this->getResource()::getUrl('index'));
        } else {
            // Si está libre o el bloqueo venció, el Admin lo pisa
            DB::table('orders')->where('id', $record->id)->update([
                'locked_by' => $userId,
                'locked_at' => $ahora,
            ]);
        }
    }

    // Al guardar o salir, liberamos el pedido
    protected function afterSave(): void
    {
        DB::table('orders')->where('id', $this->getRecord()->id)->update([
            'locked_by' => null,
            'locked_at' => null,
        ]);
    }
    
    protected function beforeSave(): void
    {
        // BLOQUEO DE GUARDADO SI ESTÁ SIENDO ARMADO
        if ($this->getRecord()->locked_at && $this->getRecord()->locked_by !== auth()->id()) {
            Notification::make()
                ->danger()
                ->title("Error al guardar")
                ->body("El pedido está bloqueado por un armador. Espera a que termine.")
                ->send();
                
            $this->halt(); 
        }
    }

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

        // CARGAR PEDIDOS HIJOS PARA VISUALIZACIÓN EN EL BLADE
        $children = Order::where('parent_id', $order->id)
            ->with(['items.sku.size', 'items.color'])
            ->get();

        $childGroupsData = [];
        foreach ($children as $child) {
            $itemsChild = $child->items->groupBy('article_id');
            $groupsChild = [];
            foreach ($itemsChild as $articleId => $articleItems) {
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
                $groupsChild[] = ['article_id' => $articleId, 'matrix' => $matrix, 'child_id' => $child->id];
            }
            $childGroupsData[] = ['id' => $child->id, 'groups' => $groupsChild];
        }
        $data['existing_children'] = $childGroupsData;

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $childGroups = $this->data['child_groups'] ?? [];
        $articleGroups = $this->data['article_groups'] ?? [];
        unset($data['child_groups'], $data['article_groups']);

        return DB::transaction(function () use ($record, $data, $articleGroups, $childGroups) {
            $oldStatus = $record->status->value;
            $record->update($data);
            $newStatus = $record->status->value;

            // SINCRONIZACIÓN DE ESTADOS A HIJOS
            $estadosFinales = ['dispatched', 'delivered', 'paid', 'cancelled'];
            if ($oldStatus !== $newStatus && in_array($newStatus, $estadosFinales)) {
                $record->children()->update(['status' => $newStatus]);
            }

            // GUARDAR PADRE (Solo Draft)
            if ($record->status->value === 'draft') {
                foreach ($articleGroups as $group) {
                    foreach ($group['matrix'] as $row) {
                        foreach ($row as $k => $val) {
                            if (str_starts_with($k, 'qty_')) {
                                $sizeId = str_replace('qty_', '', $k);
                                $sku = Sku::where('article_id', $group['article_id'])->where('color_id', $row['color_id'])->where('size_id', $sizeId)->first();
                                if ($sku) {
                                    $record->items()->updateOrCreate(['sku_id' => $sku->id], [
                                        'article_id' => $group['article_id'], 'color_id' => $row['color_id'],
                                        'quantity' => (int)($val ?? 0), 'unit_price' => $sku->article->base_cost,
                                        'subtotal' => (int)($val ?? 0) * $sku->article->base_cost
                                    ]);
                                }
                            }
                        }
                    }
                }
            }

            // CREAR HIJO (Standby)
            if ($record->status->value === 'standby' && count($childGroups) > 0) {
                $child = Order::create([
                    'parent_id' => $record->id, 'client_id' => $record->client_id, 'status' => 'processing', 
                    'order_date' => now(), 'billing_type' => $record->billing_type, 'total_amount' => 0
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
                                        'article_id' => $group['article_id'], 'sku_id' => $sku->id, 'color_id' => $row['color_id'],
                                        'quantity' => (int)$qty, 'unit_price' => $sku->article->base_cost, 'subtotal' => $sub
                                    ]);
                                    $totalChild += $sub;
                                }
                            }
                        }
                    }
                }
                $child->update(['total_amount' => $totalChild]);
                $this->data['child_groups'] = [];
                Notification::make()->title("Orden #{$child->id} generada.")->success()->send();
            }
            return $record;
        });
    }
}