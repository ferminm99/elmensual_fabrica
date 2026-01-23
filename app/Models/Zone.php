<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Zone extends Model {
    protected $fillable = ['code', 'name'];

    public function localities(): HasMany {
        return $this->hasMany(Locality::class);
    }
}