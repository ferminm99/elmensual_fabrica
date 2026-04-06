<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use App\Models\Locality; // Importamos el modelo

class MapaCobertura extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-map';
    protected static ?string $navigationGroup = 'Ventas';
    protected static ?string $navigationLabel = 'Mapa Nacional';
    protected static ?string $title = 'Cobertura Nacional de Clientes';
    
    // Dejamos la vista oficial
    protected static string $view = 'filament.pages.mapa-cobertura';

    // Le pasamos los datos directamente a la vista
    protected function getViewData(): array
    {
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
                'lat' => $locality->lat,
                'lng' => $locality->lng,
            ];
        }

        return [
            'mapData' => $mapData,
        ];
    }
}