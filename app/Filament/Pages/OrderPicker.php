<?php

namespace App\Filament\Pages;

use App\Models\Order;
use App\Models\OrderItem;
use App\Enums\OrderStatus;
use Filament\Pages\Page;
use Filament\Notifications\Notification;
use Livewire\Attributes\Computed;

class OrderPicker extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-cube-transparent';
    protected static ?string $navigationLabel = 'Armado de Pedidos';
    protected static ?string $title = 'Depósito: Armado de Cajas';
    protected static string $view = 'filament.pages.order-picker';

    public $selectedOrderId = null;
    public $packedQuantities = [];

    // Buscamos pedidos en estado 'Processing' (Para armar)
    #[Computed]
    public function pendingOrders()
    {
        return Order::where('status', OrderStatus::Processing)
            ->with(['client', 'items'])
            ->orderBy('created_at', 'asc')
            ->get();
    }

    public function selectOrder($id)
    {
        $this->selectedOrderId = $id;
        $order = Order::with(['items.article', 'items.color', 'items.size'])->find($id);
        
        $this->packedQuantities = [];
        foreach ($order->items as $item) {
            $this->packedQuantities[$item->id] = $item->packed_quantity ?? $item->quantity;
        }
    }

    public function confirmPacking()
    {
        if (!$this->selectedOrderId) return;

        foreach ($this->packedQuantities as $itemId => $qty) {
            OrderItem::where('id', $itemId)->update(['packed_quantity' => (int) $qty]);
        }

        Order::where('id', $this->selectedOrderId)->update(['status' => OrderStatus::Assembled]);

        Notification::make()->title('Pedido #' . $this->selectedOrderId . ' finalizado')->success()->send();
        $this->reset(['selectedOrderId', 'packedQuantities']);
    }
}