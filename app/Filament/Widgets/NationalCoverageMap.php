<?php

namespace App\Filament\Widgets;

use App\Models\Locality;
use Filament\Widgets\Widget;

class NationalCoverageMap extends Widget
{
    protected static string $view = 'filament.widgets.national-coverage-map';
    protected int | string | array $columnSpan = 'full';

    public function getMapData(): array
    {
        // Ahora filtramos por las que tienen latitud (no necesitamos el GeoJSON pesado)
        $localities = Locality::whereNotNull('lat')->withCount('clients')->get();
        $mapData = [];

        foreach ($localities as $locality) {
            $count = $locality->clients_count;
            $limit = $locality->client_capacity ?: 5; 

            if ($count < ($limit - 1)) {
                $color = '#22c55e'; // Verde
            } elseif ($count == ($limit - 1)) {
                $color = '#eab308'; // Amarillo
            } elseif ($count == $limit) {
                $color = '#f97316'; // Naranja
            } else {
                $color = '#ef4444'; // Rojo
            }

            $mapData[] = [
                'name' => $locality->name,
                'count' => $count,
                'limit' => $limit,
                'color' => $color,
                'lat' => $locality->lat, // Usamos el punto central
                'lng' => $locality->lng,
            ];
        }

        return $mapData;
    }
}