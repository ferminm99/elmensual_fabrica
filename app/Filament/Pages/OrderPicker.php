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
    protected static ?string $title = 'Logística y Armado';
    protected static string $view = 'filament.pages.order-picker';

    public $activeOrderId = null; 
    public $packedQuantities = []; 
    public $extraQuantities = [];

    // --- LISTAS ---
    public function getOrdersToProcessProperty()
    {
        return Order::where('status', OrderStatus::Processing)
            ->with(['client.locality'])
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

    public function selectOrder($orderId)
    {
        $this->activeOrderId = $orderId;
        $this->loadOrderData();
    }

    public function loadOrderData()
    {
        $order = $this->getActiveOrderProperty();
        $this->packedQuantities = [];
        $this->extraQuantities = [];

        if ($order) {
            foreach ($order->items as $item) {
                // Cargamos lo YA ARMADO (packed_quantity).
                // Si es NULL (nunca se tocó), el input saldrá vacío.
                $this->packedQuantities[$item->id] = $item->packed_quantity;
            }
        }
    }

    public function resetOrder()
    {
        $this->activeOrderId = null;
        $this->packedQuantities = [];
        $this->extraQuantities = [];
    }

    public function getMatrixDataProperty()
    {
        $order = $this->activeOrder; 
        if (!$order) return collect();

        return $order->items->groupBy('article_id')
            ->map(function ($items) {
                $article = $items->first()->article;
                if (!$article) return null;

                // Auto-Fix: Recuperar datos desde SKU
                $fixedItems = $items->map(function($item) {
                    $item->real_size_id = $item->size_id ?? ($item->sku ? $item->sku->size_id : null);
                    $item->real_color_id = $item->color_id ?? ($item->sku ? $item->sku->color_id : null);
                    return $item;
                });

                // 1. Talles
                $skus = Sku::where('article_id', $article->id)->get();
                $allSizeIds = $skus->pluck('size_id')->merge($fixedItems->pluck('real_size_id'))->unique()->filter();
                $dbSizes = Size::whereIn('id', $allSizeIds)->pluck('name', 'id');
                $sizes = $allSizeIds->map(fn($id) => ['id' => $id, 'name' => $dbSizes[$id] ?? 'Talle '.$id])
                    ->sortBy('name', SORT_NATURAL)->values()->all();
                if (empty($sizes)) $sizes[] = ['id' => 'null', 'name' => 'Único'];

                // 2. Colores
                $allColorIds = $skus->pluck('color_id')->merge($fixedItems->pluck('real_color_id'))->unique()->filter();
                $dbColors = Color::whereIn('id', $allColorIds)->get()->keyBy('id');
                $colors = $allColorIds->map(fn($id) => [
                    'id' => $id,
                    'name' => $dbColors[$id]->name ?? 'Color '.$id,
                    'hex' => $dbColors[$id]->hex_code ?? '#cccccc'
                ])->sortBy('name', SORT_NATURAL)->values()->all();
                if (empty($colors)) $colors[] = ['id' => 'null', 'name' => 'Varios', 'hex' => '#cccccc'];

                // 3. Grilla
                $grid = [];
                $totalReq = 0;
                $totalPack = 0;
                
                foreach ($fixedItems as $item) {
                    $cId = $item->real_color_id ?? 'null';
                    $sId = $item->real_size_id ?? 'null';
                    
                    // Lógica visual: 
                    // Si packedQuantities tiene valor (lo estamos editando), usamos ese.
                    // Si es NULL, usamos lo de la BD ($item->packed_quantity).
                    $currentPack = $this->packedQuantities[$item->id] ?? $item->packed_quantity; 

                    $grid['c_' . $cId]['s_' . $sId] = [
                        'id' => $item->id,
                        'original_req' => (int)$item->quantity, // LO PEDIDO (Referencia)
                        'current_val' => $currentPack           // LO ARMADO
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

    // --- GUARDADO INTELIGENTE (RESPETA HISTORIA) ---
    public function saveProgress()
    {
        DB::transaction(function () {
            // 1. Actualizar SOLO packed_quantity de los existentes
            foreach ($this->packedQuantities as $itemId => $qty) {
                $item = OrderItem::find($itemId);
                if ($item) {
                    // Convertimos null o vacío a 0
                    $val = ($qty === null || $qty === '') ? 0 : (int)$qty;
                    
                    // IMPORTANTE: Solo tocamos packed_quantity. 
                    // 'quantity' (lo pedido original) queda intacto.
                    $item->update(['packed_quantity' => $val]);
                }
            }

            // 2. Crear nuevos (Items que NO estaban en el pedido original)
            foreach ($this->extraQuantities as $key => $qty) {
                if ((int)$qty > 0) {
                    $parts = explode('_', $key); 
                    if (count($parts) === 3) {
                        $articleId = $parts[0];
                        $colorId = ($parts[1] === 'null') ? null : $parts[1];
                        $sizeId = ($parts[2] === 'null') ? null : $parts[2];

                        $sku = Sku::where('article_id', $articleId)
                            ->where('color_id', $colorId)
                            ->where('size_id', $sizeId)
                            ->first();

                        OrderItem::create([
                            'order_id' => $this->activeOrderId,
                            'article_id' => $articleId,
                            'sku_id' => $sku?->id,
                            'color_id' => $colorId,
                            'size_id' => $sizeId,
                            'quantity' => 0, // 0 PORQUE NADIE LO PIDIÓ (Es un agregado)
                            'packed_quantity' => $qty, // ESTO ES LO QUE SE ARMÓ
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
        
        // Verificamos si se armó algo
        $packedCount = OrderItem::where('order_id', $this->activeOrderId)->sum('packed_quantity');
        
        if ($packedCount === 0) {
            Notification::make()->title('Cuidado')->body('Estás finalizando un pedido sin ninguna prenda armada.')->warning()->send();
        }

        if ($this->activeOrder) {
            $this->activeOrder->update(['status' => OrderStatus::Assembled]); 
            Notification::make()->title('Pedido Finalizado')->success()->send();
            $this->resetOrder();
        }
    }
}