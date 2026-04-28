<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupplierInvoice extends Model
{
    protected $guarded = []; // Permitir guardar todo

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }
}