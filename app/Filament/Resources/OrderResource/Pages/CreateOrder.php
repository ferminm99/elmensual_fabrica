<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use App\Models\Sku;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class CreateOrder extends CreateRecord
{
    protected static string $resource = OrderResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        // 1. Obtenemos la matriz de artículos del estado del formulario
        $articleGroups = $this->data['article_groups'] ?? [];
        
        // Removemos los datos virtuales para que Filament no explote intentando guardarlos
        unset($data['article_groups'], $data['child_groups']);

        return DB::transaction(function () use ($data, $articleGroups) {
            // 2. Creamos el pedido (cabecera)
            $order = static::getModel()::create($data);

            $totalAmount = 0;

            // 3. Recorremos la matriz y creamos los items asociados
            foreach ($articleGroups as $group) {
                if (!isset($group['matrix']) || !isset($group['article_id'])) continue;

                foreach ($group['matrix'] as $row) {
                    if (!isset($row['color_id'])) continue;

                    foreach ($row as $k => $val) {
                        // Buscamos los campos de cantidad que sean mayores a 0
                        if (str_starts_with($k, 'qty_') && (int)$val > 0) {
                            $sizeId = str_replace('qty_', '', $k);
                            
                            $sku = Sku::where('article_id', $group['article_id'])
                                ->where('color_id', $row['color_id'])
                                ->where('size_id', $sizeId)
                                ->first();

                            if ($sku) {
                                $subtotal = (int)$val * $sku->article->base_cost;
                                
                                $order->items()->create([
                                    'article_id' => $group['article_id'],
                                    'color_id' => $row['color_id'],
                                    'sku_id' => $sku->id,
                                    'quantity' => (int)$val,
                                    'packed_quantity' => 0, // Nace sin armar
                                    'unit_price' => $sku->article->base_cost,
                                    'subtotal' => $subtotal
                                ]);

                                $totalAmount += $subtotal;
                            }
                        }
                    }
                }
            }

            // 4. Actualizamos el monto total del pedido
            $order->update(['total_amount' => $totalAmount]);

            return $order;
        });
    }
}