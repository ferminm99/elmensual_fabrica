<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierBankAccount extends Model
{
    protected $guarded = [];
    
    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }
    
    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class);
    }
}