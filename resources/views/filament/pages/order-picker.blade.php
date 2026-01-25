<x-filament-panels::page>
    <style>
        input[type=number]::-webkit-inner-spin-button, input[type=number]::-webkit-outer-spin-button { -webkit-appearance: none; margin: 0; }
        input[type=number] { -moz-appearance: textfield; }

        .sq-input { width: 48px !important; height: 38px !important; text-align: center; border-radius: 6px; font-size: 14px; outline: none; transition: all 0.2s; font-weight: bold; }

        /* ESTADOS DE DIFERENCIA */
        
        /* Pendiente: Input vacío, placeholder visible con lo pedido */
        .st-pending { background-color: #fffbeb !important; border: 2px solid #fbbf24 !important; color: #000 !important; }
        .st-pending::placeholder { color: #d97706 !important; font-weight: 800; opacity: 0.6; }

        /* OK: Lo armado == Lo pedido */
        .st-ok { background-color: #dcfce7 !important; border: 2px solid #16a34a !important; color: #14532d !important; }

        /* DIFERENCIA: Lo armado != Lo pedido (Más o menos cantidad) */
        .st-diff { background-color: #fee2e2 !important; border: 2px solid #ef4444 !important; color: #b91c1c !important; }

        /* NUEVO: No había pedido (0), pero se armó algo */
        .st-new { background-color: #bfdbfe !important; border: 2px solid #3b82f6 !important; color: #1e40af !important; }

        /* EXTRA: Vacío y sin pedido */
        .st-extra { background-color: #f9fafb; border: 1px solid #e5e7eb; color: #374151; }
        .st-extra:focus { background-color: #fff; border-color: #3b82f6; }
    </style>

    <div class="min-h-screen pb-12">
        @if(!$this->activeOrder)
            {{-- LISTAS (Igual que antes) --}}
            <div class="mb-10">
                <h2 class="text-xl font-bold text-gray-800 dark:text-white mb-4 flex items-center gap-2">
                    <span class="w-3 h-3 rounded-full bg-yellow-500"></span> PARA PREPARAR
                </h2>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    @forelse($this->ordersToProcess as $order)
                        <div wire:click="selectOrder({{ $order->id }})" class="cursor-pointer border-l-4 border-yellow-400 bg-white p-4 rounded-r-lg shadow-sm hover:shadow-md transition-all">
                            <h3 class="font-black text-lg">#{{ $order->id }}</h3>
                            <p class="text-sm font-bold text-gray-700">{{ $order->client->name ?? 'S/N' }}</p>
                            <p class="text-xs text-gray-500">{{ $order->items->count() }} Items</p>
                        </div>
                    @empty <div class="col-span-full py-4 text-center text-gray-400">Sin pendientes.</div> @endforelse
                </div>
            </div>

            @if($this->ordersReady->count() > 0)
                <div>
                    <h2 class="text-lg font-bold text-gray-700 dark:text-gray-300 mb-4 flex items-center gap-2">
                        <span class="w-3 h-3 rounded-full bg-green-500"></span> LISTOS
                    </h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 opacity-75 hover:opacity-100">
                        @foreach($this->ordersReady as $order)
                            <div wire:click="selectOrder({{ $order->id }})" class="cursor-pointer border border-gray-200 bg-white p-4 rounded-lg hover:border-green-500">
                                <h3 class="font-bold text-gray-600">#{{ $order->id }} <span class="text-xs text-green-600 float-right">LISTO</span></h3>
                                <p class="text-sm">{{ $order->client->name ?? 'S/N' }}</p>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

        @else
            {{-- MATRIZ DE ARMADO --}}
            <div class="space-y-4">
                <div class="sticky top-4 z-50 bg-white p-3 rounded-lg shadow-md border border-gray-200 flex justify-between items-center">
                    <div class="flex items-center gap-3">
                        <button wire:click="resetOrder" class="p-2 bg-gray-100 rounded-full hover:bg-gray-200"><x-heroicon-m-arrow-left class="w-5 h-5"/></button>
                        <div>
                            <h2 class="text-lg font-bold truncate">{{ $this->activeOrder->client->name }}</h2>
                            <p class="text-xs text-gray-500">#{{ $this->activeOrder->id }}</p>
                        </div>
                    </div>
                    <div class="flex gap-2">
                        <x-filament::button wire:click="saveProgress" color="gray" size="xs">Guardar</x-filament::button>
                        <x-filament::button wire:click="finalizeOrder" color="success" size="xs">Finalizar</x-filament::button>
                    </div>
                </div>

                <div class="space-y-6">
                    @foreach($this->matrixData as $group)
                        <div class="bg-white rounded-lg shadow border border-gray-300 overflow-hidden">
                            <div class="bg-gray-100 px-4 py-2 border-b border-gray-300 flex justify-between">
                                <span class="font-bold text-sm">{{ $group['article_name'] }}</span>
                                <span class="font-mono bg-white text-xs px-2 py-1 rounded border">{{ $group['article_code'] }}</span>
                            </div>
                            <div class="overflow-x-auto p-2">
                                <table class="w-auto text-xs border-collapse mx-auto">
                                    <thead>
                                        <tr>
                                            <th class="p-2 w-24 text-left">Var</th>
                                            @foreach($group['sizes'] as $size) <th class="p-2 w-12 text-center font-bold">{{ $size['name'] }}</th> @endforeach
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($group['colors'] as $color)
                                            <tr>
                                                <td class="p-2 text-left">
                                                    <div class="flex items-center gap-2">
                                                        <div class="w-3 h-3 rounded-full border" style="background-color: {{ $color['hex'] }}"></div>
                                                        <span class="truncate w-20 font-medium">{{ $color['name'] }}</span>
                                                    </div>
                                                </td>
                                                @foreach($group['sizes'] as $size)
                                                    <td class="p-1">
                                                        @php $itemData = $group['grid']['c_'.$color['id']]['s_'.$size['id']] ?? null; @endphp

                                                        @if($itemData)
                                                            @php
                                                                // DB: packed_quantity (lo que ya se armó)
                                                                // Livewire: packedQuantities (lo que editás ahora)
                                                                $val = $packedQuantities[$itemData['id']] ?? null; 
                                                                $req = $itemData['original_req']; // Lo que pidió el cliente

                                                                // Lógica Semáforo (Ahora con Diff)
                                                                if (empty($val) && $val !== 0 && $val !== '0') {
                                                                    $class = 'st-pending'; // Amarillo (No tocado)
                                                                } elseif ((int)$val === (int)$req) {
                                                                    $class = 'st-ok'; // Verde (Igual)
                                                                } else {
                                                                    $class = 'st-diff'; // Rojo (Diferente a lo pedido)
                                                                }
                                                            @endphp
                                                            <div class="flex justify-center relative">
                                                                <input type="number" placeholder="{{ $req }}" 
                                                                    wire:model.live.debounce.500ms="packedQuantities.{{ $itemData['id'] }}"
                                                                    class="sq-input {{ $class }}">
                                                                {{-- Indicador visual si hay diferencia --}}
                                                                @if((int)$val !== (int)$req && !empty($val))
                                                                    <div class="absolute -top-1 -right-1 w-2 h-2 bg-red-500 rounded-full animate-pulse"></div>
                                                                @endif
                                                            </div>
                                                        @else
                                                            @php
                                                                $extraKey = $group['article_id'] . '_' . $color['id'] . '_' . $size['id'];
                                                                $qtyExtra = $extraQuantities[$extraKey] ?? 0;
                                                                $extraClass = (int)$qtyExtra > 0 ? 'st-new' : 'st-extra';
                                                            @endphp
                                                            <div class="flex justify-center">
                                                                <input type="number" placeholder="-"
                                                                    wire:model.live.debounce.500ms="extraQuantities.{{ $extraKey }}"
                                                                    class="sq-input {{ $extraClass }}">
                                                            </div>
                                                        @endif
                                                    </td>
                                                @endforeach
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>