<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;
use App\Enums\Origin;

trait HasAccountingType
{
    public static function bootHasAccountingType()
    {
        static::addGlobalScope('accounting_origin', function (Builder $builder) {
            // By default, you might want to show both or filter by session.
            // This is a placeholder for your specific logic (e.g., session('active_origin'))
            // For now, we leave it open or simple.
            
            // Example usage in controller: Transaction::withoutGlobalScope('accounting_origin')->get();
        });
    }

    public function scopeFiscal(Builder $query): void
    {
        $query->where('origin', Origin::FISCAL);
    }

    public function scopeInternal(Builder $query): void
    {
        $query->where('origin', Origin::INTERNAL);
    }
}