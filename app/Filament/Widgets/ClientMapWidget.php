<?php

namespace App\Filament\Resources\ClientResource\Widgets;

use App\Models\Client;
use Filament\Widgets\Widget;

class ClientMapWidget extends Widget
{
    protected static string $view = 'filament.resources.client-resource.widgets.client-map-widget';

    public function getClientMarkers(): array
    {
        return Client::whereNotNull('lat')->whereNotNull('lng')->get()->map(function($client) {
            
            // Logic for Color based on Debt
            $color = 'green';
            if ($client->account_balance_fiscal < -100000) $color = 'red';
            elseif ($client->account_balance_fiscal < 0) $color = 'yellow';

            return [
                'lat' => $client->lat,
                'lng' => $client->lng,
                'name' => $client->name,
                'color' => $color,
            ];
        })->toArray();
    }
}