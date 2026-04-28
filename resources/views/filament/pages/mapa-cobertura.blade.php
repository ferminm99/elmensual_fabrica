<x-filament-panels::page>
    <x-filament::card>
        <div 
            wire:ignore 
            x-data="{
                init() {
                    // 1. Cargar el CSS de Leaflet dinámicamente
                    if (!document.getElementById('leaflet-css')) {
                        const link = document.createElement('link');
                        link.id = 'leaflet-css';
                        link.rel = 'stylesheet';
                        link.href = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
                        document.head.appendChild(link);
                    }

                    // 2. Cargar el JS de Leaflet dinámicamente
                    if (!document.getElementById('leaflet-js')) {
                        const script = document.createElement('script');
                        script.id = 'leaflet-js';
                        script.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
                        script.onload = () => this.renderMap();
                        document.head.appendChild(script);
                    } else {
                        this.renderMap();
                    }
                },
                renderMap() {
                    // Evitar que se duplique el mapa si cambias de pestaña
                    if (window.myMapInstance) {
                        window.myMapInstance.remove();
                    }
                    
                    var map = L.map('argentinaMap').setView([-36.0, -60.0], 7);
                    window.myMapInstance = map;

                    L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', {
                        attribution: '&copy; OpenStreetMap'
                    }).addTo(map);

                    // Traemos los datos desde PHP a Javascript de forma segura
                    var data = {{ json_encode($mapData) }};
                    
                    data.forEach(function(loc) {
                        if (loc.lat && loc.lng) {
                            var marker = L.circleMarker([loc.lat, loc.lng], {
                                radius: 10,
                                fillColor: loc.color,
                                color: '#ffffff',
                                weight: 2,
                                opacity: 1,
                                fillOpacity: 0.9
                            }).addTo(map);

                            marker.bindPopup(
                                '<div style=\'text-align:center; font-family: sans-serif; min-width: 150px;\'>' +
                                    '<b style=\'font-size:16px; display:block; margin-bottom:5px;\'>' + loc.name + '</b>' +
                                    '<div style=\'background-color:#f3f4f6; padding:5px; border-radius:4px; margin-bottom:10px;\'>' +
                                        '<span style=\'color:' + loc.color + '; font-weight:bold; font-size:13px;\'>' + 
                                            'Clientes: ' + loc.count + ' / ' + loc.limit + 
                                        '</span>' +
                                    '</div>' +
                                    '<a href=\'/admin/localities/' + loc.id + '/edit\' ' +
                                    'style=\'display:inline-block; background-color:' + loc.color + '; color:white; padding:8px 12px; border-radius:6px; text-decoration:none; font-weight:bold; font-size:12px;\'>' +
                                    'VER ' + loc.name.toUpperCase() +
                                    '</a>' +
                                '</div>'
                            );
                        }
                    });
                }
            }"
        >
            <div id="argentinaMap" style="height: 600px; width: 100%; z-index: 10; border-radius: 8px;"></div>
        </div>
    </x-filament::card>
</x-filament-panels::page>