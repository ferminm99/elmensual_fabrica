<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class CreateOrder extends CreateRecord
{
    protected static string $resource = OrderResource::class;

    // ESTA FUNCIÓN ES LA MAGIA: Intercepta el guardado
    protected function handleRecordCreation(array $data): Model
    {
        // 1. Sacamos los grupos "virtuales" del formulario
        $articleGroups = $data['article_groups'] ?? [];
        
        // 2. Los borramos de la data principal para que no fallen al crear la Orden
        unset($data['article_groups']);
        
        // 3. Iniciamos una transacción (por seguridad)
        return DB::transaction(function () use ($data, $articleGroups) {
            
            // Creamos la cabecera del pedido (Cliente, Fecha, Estado)
            $order = static::getModel()::create($data);

            $totalAmount = 0;

            // 4. Recorremos los grupos y guardamos los items reales
            foreach ($articleGroups as $group) {
                $articleId = $group['article_id'];
                $variants = $group['variants'] ?? [];

                foreach ($variants as $variant) {
                    // Calculamos subtotal
                    $qty = intval($variant['quantity']);
                    $price = floatval($variant['unit_price']);
                    $subtotal = $qty * $price;

                    // Guardamos el Item en la base de datos
                    // IMPORTANTE: Asumo que tu tabla se llama 'order_items'
                    $order->items()->create([
                        'article_id' => $articleId,
                        'sku_id'     => $variant['sku_id'],
                        'color_id'   => $variant['color_id'],
                        'quantity'   => $qty,
                        'unit_price' => $price,
                        'subtotal'   => $subtotal,
                    ]);

                    $totalAmount += $subtotal;
                }
            }

            // 5. Actualizamos el total final en la orden
            $order->update(['total_amount' => $totalAmount]);

            return $order;
        });
    }
    
}