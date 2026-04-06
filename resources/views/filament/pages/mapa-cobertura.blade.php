<div wire:ignore>
    <x-filament-panels::page>
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

        <x-filament::card>
            <div id="argentinaMap" style="height: 600px; width: 100%; z-index: 10; border-radius: 8px;"></div>
        </x-filament::card>

        <script>
            document.addEventListener('DOMContentLoaded', function () {
                if (window.myMapInstance) return;

                var map = L.map('argentinaMap').setView([-36.0, -60.0], 7);
                window.myMapInstance = map;

                L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', {
                    attribution: '&copy; OpenStreetMap'
                }).addTo(map);

                var data = @json($mapData);

                data.forEach(function(loc) {
                    if (loc.lat && loc.lng) {
                        L.circleMarker([loc.lat, loc.lng], {
                            radius: 10,
                            fillColor: loc.color,
                            color: '#ffffff',
                            weight: 2,
                            opacity: 1,
                            fillOpacity: 0.9
                        }).addTo(map)
                        .bindPopup(
                            '<div style="text-align:center;">' +
                            '<b style="font-size:14px;">' + loc.name + '</b><br>' +
                            '<span style="color:' + loc.color + '; font-weight:bold;">' + 
                            'Clientes: ' + loc.count + ' / Límite: ' + loc.limit + 
                            '</span></div>'
                        );
                    }
                });
            });
        </script>
    </x-filament-panels::page>
</div>