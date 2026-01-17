<?php

namespace App\Observers;

use App\Models\ProductionOrder;
use App\Models\Sku;
use App\Enums\ProductionStatus;
use App\Models\RawMaterial;
use Illuminate\Support\Facades\DB;

class ProductionOrderObserver
{
    public function updated(ProductionOrder $productionOrder): void
    {
        // Check if status just changed to 'Finished'
        if ($productionOrder->isDirty('status') && $productionOrder->status === 'Finished') {
            
            DB::transaction(function () use ($productionOrder) {
                $article = $productionOrder->article;
                $quantity = $productionOrder->quantity;

                // 1. Decrement Raw Materials (based on Recipe)
                foreach ($article->recipes as $recipe) {
                    $totalRequired = $recipe->quantity_required * $quantity;
                    // Add waste calculation if needed
                    // $totalRequired += ($totalRequired * ($recipe->waste_percent / 100));

                    $rawMaterial = $recipe->rawMaterial;
                    $rawMaterial->decrement('stock_quantity', $totalRequired);
                }

                // 2. Increment Finished Product Stock
                // Note: Production Order usually targets a specific Article. 
                // However, stock is held in SKUs (Size/Color). 
                // If the Production Order doesn't specify Size/Color, 
                // you might need to split this order or default to a specific SKU.
                
                // Assuming for this example the ProductionOrder has specific SKU logic 
                // or we are just adding to a generic 'Base' SKU if your logic allows.
                
                // Ideally, ProductionOrder should have sku_id, not article_id. 
                // If it relies on article_id, we can't know WHICH size to increment.
                // assuming you add sku_id to production_orders later:
                // Sku::find($productionOrder->sku_id)->increment('stock_quantity', $quantity);
            });
        }
    }
}