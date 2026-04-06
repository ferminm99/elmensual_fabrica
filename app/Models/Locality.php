<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Locality extends Model {
    // Agregamos lat y lng acá
    protected $fillable = ['code', 'name', 'zone_id', 'lat', 'lng', 'geojson']; // <-- agregado

    protected $casts = [
        'geojson' => 'array', // Para que Laravel lo maneje fácil
    ];

    public function zone(): BelongsTo {
        return $this->belongsTo(Zone::class);
    }

    public function clients(): HasMany {
        return $this->hasMany(Client::class);
    }
}