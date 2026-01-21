<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RawMaterialStock extends Model
{
    protected $guarded = [];

    public function rawMaterial() { return $this->belongsTo(RawMaterial::class); }
    public function color() { return $this->belongsTo(Color::class); }
}