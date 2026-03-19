<?php

namespace App\Filament\Resources\InvoiceResource\Pages;

use App\Filament\Resources\InvoiceResource;
use App\Models\Order;
use App\Models\OrderItem;
use App\Enums\OrderStatus;
use App\Services\AfipService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\DB;
use Exception;

class CreateInvoice extends CreateRecord
{
    protected static string $resource = InvoiceResource::class;

    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        return DB::transaction(function () use ($data) {
            $esNC = ($data['tipo_manual'] ?? 'factura') === 'nc';

            // 1. Crear el Pedido Fantasma
            $order = Order::create([
                'client_id' => $data['client_id'] ?? null,
                'status' => OrderStatus::Checked,
                'billing_type' => 'fiscal',
                'order_date' => now(),
                'total_amount' => 0,
                'notes' => "Carga Manual: " . ($data['manual_client_name'] ?? 'Cliente Registrado'),
            ]);

            $totalMonto = 0;
            foreach ($data['items'] as $item) {
                $sub = $item['quantity'] * $item['unit_price'];
                $totalMonto += $sub;

                OrderItem::create([
                    'order_id' => $order->id,
                    'article_id' => $item['article_id'],
                    'quantity' => $item['quantity'],
                    'packed_quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'subtotal' => $sub,
                ]);
            }

            $order->update(['total_amount' => $totalMonto]);

            // 2. Llamada dinámica a AFIP
            // Si es NC, le pasamos el tipo 8 (NC B)
            $tipoAfip = $esNC ? 8 : 6; 
            
            $res = AfipService::facturar($order, [
                'billing_type' => 'fiscal',
                'voucher_type' => $tipoAfip, // <--- Nueva variable
                'manual_tax_id' => $data['manual_client_tax_id'] ?? null,
            ]);

            if (!$res['success']) {
                throw new Exception("Error AFIP: " . $res['error']);
            }

            return $order->invoices()->latest()->first();
        });
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}