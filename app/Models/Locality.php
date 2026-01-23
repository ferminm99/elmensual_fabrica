<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Locality extends Model {
    protected $fillable = ['code', 'name', 'zone_id'];

    public function zone(): BelongsTo {
        return $this->belongsTo(Zone::class);
    }

    public function clients(): HasMany {
        return $this->hasMany(Client::class);
    }
}