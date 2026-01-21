<?php

namespace App\Providers;
use App\Models\OrderItem;
use App\Observers\OrderItemObserver;
use App\Models\ProductionOrder;
use App\Observers\ProductionOrderObserver;
use App\Models\StockMovement;
use App\Observers\StockMovementObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
        ProductionOrder::observe(ProductionOrderObserver::class);
        StockMovement::observe(StockMovementObserver::class);
        OrderItem::observe(OrderItemObserver::class);
    }
}