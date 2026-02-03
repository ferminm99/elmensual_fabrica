<?php

namespace App\Filament\Pages;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Sku;
use App\Models\Size;
use App\Models\Color;
use App\Enums\OrderStatus;
use Filament\Pages\Page;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;

class OrderPicker extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-cube-transparent';
    protected static ?string $navigationLabel = 'Armado de Pedidos';
    protected static ?string $navigationGroup = 'Ventas';
    protected static ?string $title = 'Logística y Armado';
    protected static string $view = 'filament.pages.order-picker';

    public $activeOrderId = null; 
    public $packedQuantities = []; 
    public $extraQuantities = [];

    public function getOrdersToProcessProperty()
    {
        return Order::where('status', OrderStatus::Processing)
            ->with(['client.locality', 'client.locality.zone'])
            ->orderBy('priority', 'desc') 
            ->orderBy('order_date', 'asc') 
            ->get();
    }

    public function getOrdersReadyProperty()
    {
        return Order::where('status', OrderStatus::Assembled)
            ->with(['client.locality'])
            ->latest('updated_at')
            ->get();
    }

    public function getActiveOrderProperty()
    {
        if (!$this->activeOrderId) return null;
        return Order::with(['items.article', 'items.color', 'items.size', 'items.sku', 'client'])
            ->find($this->activeOrderId);
    }

    public function keepAlive()
    {
        if ($this->activeOrderId) {
            DB::table('orders')->where('id', $this->activeOrderId)->update([
                'locked_at' => now(),
            ]);
        }
    }

    public function selectOrder($orderId)
    {
        $order = Order::find($orderId);
        $userId = auth()->id();
        $ahora = now();

        // Solo bloquear si el bloqueo actual tiene menos de 2 minutos
        $isLocked = $order->locked_at && $order->locked_at->diffInMinutes($ahora) < 2;

        if ($isLocked && $order->locked_by !== $userId) {
            Notification::make()->warning()->title("Pedido Ocupado")->send();
            return;
        }

        DB::table('orders')->where('id', $orderId)->update([
            'locked_by' => $userId,
            'locked_at' => $ahora,
        ]);

        $this->activeOrderId = $orderId;
        $this->loadOrderData();
    }

    public function resetOrder()
    {
        if ($this->activeOrderId) {
            // LIBERAR EL PEDIDO AL SALIR
            DB::table('orders')->where('id', $this->activeOrderId)->update([
                'locked_by' => null,
                'locked_at' => null,
            ]);
        }
        $this->activeOrderId = null;
        $this->packedQuantities = [];
        $this->extraQuantities = [];
    }
    
    public function clearOrder()
    {
        $this->resetOrder();
    }

    public function loadOrderData()
    {
        $order = $this->getActiveOrderProperty();
        $this->packedQuantities = [];
        $this->extraQuantities = [];

        if ($order) {
            foreach ($order->items as $item) {
                $this->packedQuantities[$item->id] = $item->packed_quantity;
            }
        }
    }

    public function getMatrixDataProperty()
    {
        $order = $this->activeOrder; 
        if (!$order) return collect();

        return $order->items->groupBy('article_id')
            ->map(function ($items) {
                $article = $items->first()->article;
                if (!$article) return null;

                $fixedItems = $items->map(function($item) {
                    $item->real_size_id = $item->size_id ?? ($item->sku ? $item->sku->size_id : null);
                    $item->real_color_id = $item->color_id ?? ($item->sku ? $item->sku->color_id : null);
                    return $item;
                });

                $skus = Sku::where('article_id', $article->id)->get();
                $allSizeIds = $skus->pluck('size_id')->merge($fixedItems->pluck('real_size_id'))->unique()->filter();
                $dbSizes = Size::whereIn('id', $allSizeIds)->pluck('name', 'id');
                $sizes = $allSizeIds->map(fn($id) => ['id' => $id, 'name' => $dbSizes[$id] ?? 'Talle '.$id])
                    ->sortBy('name', SORT_NATURAL)->values()->all();
                if (empty($sizes)) $sizes[] = ['id' => 'null', 'name' => 'Único'];

                $allColorIds = $skus->pluck('color_id')->merge($fixedItems->pluck('real_color_id'))->unique()->filter();
                $dbColors = Color::whereIn('id', $allColorIds)->get()->keyBy('id');
                $colors = $allColorIds->map(fn($id) => [
                    'id' => $id,
                    'name' => $dbColors[$id]->name ?? 'Color '.$id,
                    'hex' => $dbColors[$id]->hex_code ?? '#cccccc'
                ])->sortBy('name', SORT_NATURAL)->values()->all();
                if (empty($colors)) $colors[] = ['id' => 'null', 'name' => 'Varios', 'hex' => '#cccccc'];

                $grid = [];
                $totalReq = 0;
                $totalPack = 0;
                
                foreach ($fixedItems as $item) {
                    $cId = $item->real_color_id ?? 'null';
                    $sId = $item->real_size_id ?? 'null';
                    $currentPack = $this->packedQuantities[$item->id] ?? $item->packed_quantity; 

                    $grid['c_' . $cId]['s_' . $sId] = [
                        'id' => $item->id,
                        'original_req' => (int)$item->quantity,
                        'current_val' => $currentPack
                    ];
                    
                    $totalReq += (int)$item->quantity;
                    $totalPack += (int)$currentPack;
                }

                return [
                    'article_id' => $article->id,
                    'article_name' => $article->name,
                    'article_code' => $article->code,
                    'sizes' => $sizes,
                    'colors' => $colors,
                    'grid' => $grid,
                    'total_items' => $totalReq,
                    'total_packed' => $totalPack,
                    'is_complete' => ($totalReq > 0 && $totalReq === $totalPack)
                ];
            })
            ->filter(); 
    }

    public function saveProgress()
    {
        DB::transaction(function () {
            foreach ($this->packedQuantities as $itemId => $qty) {
                $item = OrderItem::find($itemId);
                if ($item) {
                    $val = ($qty === null || $qty === '') ? 0 : (int)$qty;
                    $item->update(['packed_quantity' => $val]);
                }
            }

            foreach ($this->extraQuantities as $key => $qty) {
                if ((int)$qty > 0) {
                    $parts = explode('_', $key); 
                    if (count($parts) === 3) {
                        $articleId = $parts[0];
                        $colorId = ($parts[1] === 'null') ? null : $parts[1];
                        $sizeId = ($parts[2] === 'null') ? null : $parts[2];
                        $sku = Sku::where('article_id', $articleId)->where('color_id', $colorId)->where('size_id', $sizeId)->first();

                        OrderItem::create([
                            'order_id' => $this->activeOrderId,
                            'article_id' => $articleId,
                            'sku_id' => $sku?->id,
                            'color_id' => $colorId,
                            'size_id' => $sizeId,
                            'quantity' => 0, 
                            'packed_quantity' => $qty,
                            'unit_price' => $sku ? $sku->article->base_cost : 0,
                        ]);
                    }
                }
            }
        });

        $this->extraQuantities = [];
        $this->loadOrderData();
        Notification::make()->title('Progreso Guardado')->success()->send();
    }

    public function finalizeOrder()
    {
        $this->saveProgress();
        if ($this->activeOrderId) {
            Order::where('id', $this->activeOrderId)->update([
                'status' => OrderStatus::Assembled,
                'locked_by' => null, 
                'locked_at' => null
            ]); 
            Notification::make()->title('Pedido Finalizado')->success()->send();
            $this->resetOrder();
        }
    }
}