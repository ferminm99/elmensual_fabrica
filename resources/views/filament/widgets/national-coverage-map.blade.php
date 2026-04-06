<x-filament-widgets::widget>
    <x-filament::card>
        <h2 class="text-xl font-bold mb-4">Mapa de Cobertura y Saturación</h2>
        
        <div id="argentinaMap" style="height: 600px; width: 100%; z-index: 1; border-radius: 8px;"></div>

        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

        <script>
            document.addEventListener('DOMContentLoaded', function () {
                var map = L.map('argentinaMap').setView([-36.0, -60.0], 7);

                L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', {
                    attribution: '&copy; OpenStreetMap'
                }).addTo(map);

                var data = @json($this->getMapData());

                data.forEach(function(loc) {
                    if (loc.lat && loc.lng) {
                        // Usamos circleMarker: carga al instante y queda genial
                        L.circleMarker([loc.lat, loc.lng], {
                            radius: 10, // Qué tan grande es el círculo
                            fillColor: loc.color, // Color del semáforo
                            color: '#ffffff', // Borde blanco
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
    </x-filament::card>
</x-filament-widgets::widget>