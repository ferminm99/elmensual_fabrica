<?php

namespace App\Filament\Pages;

use App\Models\Order;
use App\Models\OrderItem;
use Filament\Pages\Page;
use Filament\Notifications\Notification;

class OrderPicker extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-cube-transparent';
    protected static ?string $navigationLabel = 'Armado de Pedidos';
    protected static ?string $title = 'Depósito: Armado de Cajas';
    protected static string $view = 'filament.pages.order-picker';

    public $orderId;
    public $packedQuantities = []; // Aquí guardamos los inputs del armador

    // Cargamos el pedido seleccionado
    public function loadOrder($id)
    {
        $this->orderId = $id;
        $order = Order::with('items.article', 'items.color', 'items.size')->find($id);
        
        foreach ($order->items as $item) {
            // Por defecto, sugerimos la cantidad pedida
            $this->packedQuantities[$item->id] = $item->packed_quantity ?? $item->quantity;
        }
    }

    public function confirmPacking()
    {
        $order = Order::find($this->orderId);

        foreach ($this->packedQuantities as $itemId => $quantity) {
            $item = OrderItem::find($itemId);
            $item->update(['packed_quantity' => $quantity]);
            
            // Aquí podrías disparar el ajuste de STOCK REAL
            // $item->sku->decrementStock($quantity); 
        }

        $order->update(['status' => 'packed']); // O el enum que uses

        Notification::make()->title('Pedido Armado Correctamente')->success()->send();
        $this->reset(['orderId', 'packedQuantities']);
    }
}