<x-filament-panels::page>
    <style>
        input[type=number]::-webkit-inner-spin-button, input[type=number]::-webkit-outer-spin-button { -webkit-appearance: none; margin: 0; }
        input[type=number] { -moz-appearance: textfield; }
        .sq-input { width: 48px !important; height: 38px !important; text-align: center; border-radius: 6px; font-size: 14px; outline: none; transition: all 0.2s; font-weight: bold; }
        .st-pending { background-color: #fffbeb !important; border: 2px solid #fbbf24 !important; color: #000 !important; }
        .st-pending::placeholder { color: #d97706 !important; font-weight: 800; opacity: 0.6; }
        .st-ok { background-color: #dcfce7 !important; border: 2px solid #16a34a !important; color: #14532d !important; }
        .st-diff { background-color: #fee2e2 !important; border: 2px solid #ef4444 !important; color: #b91c1c !important; }
        .st-new { background-color: #bfdbfe !important; border: 2px solid #3b82f6 !important; color: #1e40af !important; }
        .st-extra { background-color: #f9fafb; border: 1px solid #e5e7eb; color: #374151; }
        .st-extra:focus { background-color: #fff; border-color: #3b82f6; }
    </style>

    <div class="min-h-screen pb-12">
        @if(!$this->activeOrder)
            {{-- LISTA DE PENDIENTES --}}
            <div class="mb-12">
                <div class="flex items-center gap-3 mb-4 border-b border-gray-200 pb-2">
                    <span class="flex h-8 w-8 items-center justify-center rounded-full bg-yellow-100 text-yellow-600"><x-heroicon-m-clipboard-document-list class="h-5 w-5" /></span>
                    <h2 class="text-xl font-bold text-gray-800 dark:text-white">PARA PREPARAR</h2>
                    <span class="rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-800">{{ $this->ordersToProcess->count() }}</span>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    @forelse($this->ordersToProcess as $order)
                        @php
                            // Lógica de estilos según prioridad
                            $priorityClass = match($order->priority) {
                                3 => 'ring-2 ring-red-500 bg-red-50 dark:bg-red-900/20', // Urgente
                                2 => 'ring-2 ring-orange-400 bg-orange-50 dark:bg-orange-900/10', // Alta
                                default => 'ring-1 ring-gray-200 bg-white dark:bg-gray-800 dark:ring-gray-700', // Normal
                            };

                            $priorityBadge = match($order->priority) {
                                3 => '<span class="animate-pulse bg-red-600 text-white text-[10px] font-black px-2 py-1 rounded uppercase tracking-wider shadow-sm">🔥 URGENTE</span>',
                                2 => '<span class="bg-orange-500 text-white text-[10px] font-bold px-2 py-1 rounded uppercase shadow-sm">⚡ ALTA</span>',
                                default => '<span class="bg-gray-100 text-gray-600 text-[10px] font-bold px-2 py-1 rounded uppercase">NORMAL</span>',
                            };
                        @endphp

                        <div wire:click="selectOrder({{ $order->id }})" 
                            class="group cursor-pointer relative overflow-hidden rounded-xl p-4 shadow-sm transition-all hover:shadow-md hover:scale-[1.02] {{ $priorityClass }}">
                            
                            <div class="flex justify-between items-start mb-2">
                                <div>
                                    <h3 class="text-2xl font-black text-gray-900 dark:text-white">#{{ $order->id }}</h3>
                                    <p class="font-bold text-lg text-gray-700 truncate leading-tight">{{ $order->client->name ?? 'S/N' }}</p>
                                </div>
                                {!! $priorityBadge !!}
                            </div>
                            
                            <div class="mt-3 flex flex-wrap gap-2">
                                <span class="inline-flex items-center px-2 py-1 rounded text-xs font-bold bg-blue-100 text-blue-800 border border-blue-200">
                                    <x-heroicon-m-map-pin class="w-3 h-3 mr-1"/> {{ $order->client->locality->name ?? 'Sin Loc' }}
                                </span>
                                @if($order->client->locality?->zone)
                                    <span class="inline-flex items-center px-2 py-1 rounded text-xs font-bold bg-purple-100 text-purple-800 border border-purple-200">
                                        <x-heroicon-m-globe-americas class="w-3 h-3 mr-1"/> {{ $order->client->locality->zone->name }}
                                    </span>
                                @endif
                            </div>

                            <div class="mt-2 text-xs text-gray-400 flex justify-between pt-2 border-t border-gray-200/50 dark:border-gray-700">
                                <span>{{ $order->items->count() }} ítems</span>
                                <span>{{ $order->order_date->format('d/m/Y') }}</span>
                            </div>
                        </div>
                    @empty 
                        <div class="col-span-full py-12 text-center">
                            <div class="flex justify-center mb-4">
                                <div class="p-4 bg-gray-50 rounded-full dark:bg-gray-800">
                                    <x-heroicon-o-check-circle class="w-12 h-12 text-gray-300"/>
                                </div>
                            </div>
                            <h3 class="text-lg font-medium text-gray-900 dark:text-white">Todo listo por hoy</h3>
                            <p class="text-gray-500">No hay pedidos pendientes de preparación.</p>
                        </div> 
                    @endforelse
                </div>
            </div>

            {{-- LISTA DE PREPARADOS --}}
            @if($this->ordersReady->count() > 0)
                <div>
                    <div class="flex items-center gap-3 mb-4 border-b border-gray-200 pb-2">
                        <span class="flex h-8 w-8 items-center justify-center rounded-full bg-green-100 text-green-600"><x-heroicon-m-check-badge class="h-5 w-5" /></span>
                        <h2 class="text-xl font-bold text-gray-800 dark:text-white">PREPARADOS / LISTOS</h2>
                        <span class="rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-800">{{ $this->ordersReady->count() }}</span>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 opacity-80 hover:opacity-100 transition-opacity">
                        @foreach($this->ordersReady as $order)
                            <div wire:click="selectOrder({{ $order->id }})" class="cursor-pointer rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200 transition-all hover:ring-green-500 dark:bg-gray-800 dark:ring-gray-700">
                                <div class="flex justify-between items-start mb-2">
                                    <div>
                                        <h3 class="text-lg font-bold text-gray-600 dark:text-gray-300">#{{ $order->id }}</h3>
                                        <p class="font-medium text-gray-600 truncate">{{ $order->client->name ?? 'S/N' }}</p>
                                    </div>
                                    <span class="bg-green-100 text-green-800 text-[10px] font-bold px-2 py-1 rounded uppercase flex items-center gap-1"><x-heroicon-m-check class="w-3 h-3"/> LISTO</span>
                                </div>
                                <div class="mt-2 text-xs text-gray-500">
                                    {{ $order->client->locality->name ?? '-' }} 
                                    @if($order->client->locality?->zone) - {{ $order->client->locality->zone->name }} @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

        @else
            {{-- VISTA MATRIZ (Igual que antes) --}}
            <div class="space-y-4">
                <div class="sticky top-4 z-50 bg-white dark:bg-gray-900 p-3 rounded-lg shadow-md border border-gray-200 dark:border-gray-700 flex justify-between items-center">
                    <div class="flex items-center gap-3 overflow-hidden">
                        <button wire:click="resetOrder" class="p-2 bg-gray-100 rounded-full hover:bg-gray-200 dark:bg-gray-800 dark:text-white shrink-0"><x-heroicon-m-arrow-left class="w-5 h-5"/></button>
                        <div class="truncate">
                            <h2 class="text-lg font-bold dark:text-white truncate">{{ $this->activeOrder->client->name }}</h2>
                            <p class="text-xs text-gray-500 font-bold">
                                #{{ $this->activeOrder->id }} &bull; 
                                {{ $this->activeOrder->client->locality->name ?? '' }}
                                @if($this->activeOrder->client->locality?->zone) 
                                    &bull; {{ $this->activeOrder->client->locality->zone->name }}
                                @endif
                            </p>
                        </div>
                    </div>
                    <div class="flex gap-2 shrink-0">
                        <x-filament::button wire:click="saveProgress" color="gray" size="xs">Guardar</x-filament::button>
                        <x-filament::button wire:click="finalizeOrder" color="success" size="xs">{{ $this->activeOrder->status === \App\Enums\OrderStatus::Assembled ? 'Actualizar' : 'Finalizar' }}</x-filament::button>
                    </div>
                </div>

                <div class="space-y-6">
                    @foreach($this->matrixData as $group)
                        <div class="bg-white dark:bg-gray-900 rounded-lg shadow border border-gray-300 dark:border-gray-700 overflow-hidden">
                            <div class="bg-gray-100 dark:bg-gray-800 px-4 py-2 border-b dark:border-gray-700 flex justify-between items-center">
                                <div class="flex items-center gap-2 overflow-hidden"><span class="font-bold text-sm text-gray-900 dark:text-white truncate">{{ $group['article_name'] }}</span></div>
                                <span class="font-mono bg-white text-xs px-2 py-1 rounded border">{{ $group['article_code'] }}</span>
                            </div>
                            <div class="overflow-x-auto p-2">
                                <table class="w-auto text-xs border-collapse mx-auto">
                                    <thead>
                                        <tr class="border-b border-gray-200 dark:border-gray-700">
                                            <th class="p-2 w-24 text-left font-medium text-gray-500 sticky left-0 bg-white dark:bg-gray-900 z-10">Variante</th>
                                            @foreach($group['sizes'] as $size) <th class="p-2 w-12 text-center font-bold text-gray-700 dark:text-gray-300">{{ $size['name'] }}</th> @endforeach
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                        @foreach($group['colors'] as $color)
                                            <tr>
                                                <td class="p-2 text-left sticky left-0 bg-white dark:bg-gray-900 z-10 border-r border-gray-100 dark:border-gray-700">
                                                    <div class="flex items-center gap-2"><div class="w-3 h-3 rounded-full border shadow-sm" style="background-color: {{ $color['hex'] }}"></div><span class="font-medium truncate w-20 dark:text-gray-200">{{ $color['name'] }}</span></div>
                                                </td>
                                                @foreach($group['sizes'] as $size)
                                                    <td class="p-1 text-center">
                                                        @php $itemData = $group['grid']['c_'.$color['id']]['s_'.$size['id']] ?? null; @endphp
                                                        @if($itemData)
                                                            @php
                                                                $val = $packedQuantities[$itemData['id']] ?? null; 
                                                                $req = $itemData['original_req']; 
                                                                if (empty($val) && $val !== 0 && $val !== '0') $class = 'st-pending';
                                                                elseif ((int)$val === (int)$req) $class = 'st-ok';
                                                                else $class = 'st-diff';
                                                            @endphp
                                                            <div class="flex justify-center relative">
                                                                <input type="number" placeholder="{{ $req }}" wire:model.live.debounce.500ms="packedQuantities.{{ $itemData['id'] }}" class="sq-input {{ $class }}">
                                                                @if((int)$val !== (int)$req && !empty($val)) <div class="absolute -top-1 -right-1 w-2 h-2 bg-red-500 rounded-full animate-pulse"></div> @endif
                                                            </div>
                                                        @else
                                                            @php
                                                                $extraKey = $group['article_id'] . '_' . $color['id'] . '_' . $size['id'];
                                                                $qtyExtra = $extraQuantities[$extraKey] ?? 0;
                                                                $extraClass = (int)$qtyExtra > 0 ? 'st-new' : 'st-extra';
                                                            @endphp
                                                            <div class="flex justify-center relative">
                                                                <input type="number" placeholder="-" wire:model.live.debounce.500ms="extraQuantities.{{ $extraKey }}" class="sq-input {{ $extraClass }}">
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