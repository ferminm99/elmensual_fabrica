<x-filament-panels::page>
    <style>
        input[type=number]::-webkit-inner-spin-button, input[type=number]::-webkit-outer-spin-button { -webkit-appearance: none; margin: 0; }
        input[type=number] { -moz-appearance: textfield; }
        .sq-input { width: 48px !important; height: 38px !important; text-align: center; border-radius: 6px; font-size: 14px; outline: none; transition: all 0.2s; font-weight: bold; border: 2px solid transparent; }
        
        .sq-input::placeholder { color: #9ca3af; font-weight: 900; opacity: 0.6; }
        
        .st-pending { background-color: #fef3c7 !important; border: 2px solid #fbbf24 !important; color: #92400e !important; }
        .dark .st-pending { background-color: #451a03 !important; border-color: #b45309 !important; color: #fbbf24 !important; }
        
        .st-ok { background-color: #dcfce7 !important; border: 2px solid #22c55e !important; color: #15803d !important; }
        .dark .st-ok { background-color: #064e3b !important; border-color: #10b981 !important; color: #34d399 !important; }
        
        .st-diff { background-color: #fee2e2 !important; border: 2px solid #ef4444 !important; color: #b91c1c !important; }
        .dark .st-diff { background-color: #450a0a !important; border-color: #dc2626 !important; color: #f87171 !important; }
        
        .st-extra { background-color: #f3f4f6 !important; border: 1px solid #d1d5db !important; color: #374151 !important; }
        .dark .st-extra { background-color: #1e293b !important; border: 1px solid #334155 !important; color: #94a3b8 !important; }
        
        .st-new { background-color: #dbeafe !important; border: 2px solid #3b82f6 !important; color: #1e3a8a !important; }
        .dark .st-new { background-color: #172554 !important; border-color: #2563eb !important; color: #60a5fa !important; }
    </style>

    <div class="min-h-screen pb-12" @if($activeOrderId) wire:poll.60s="keepAlive" @endif>
        @if(!$this->activeOrder)
            {{-- LISTA DE PENDIENTES --}}
            <div class="mb-12">
                <div class="flex items-center gap-3 mb-4 border-b border-gray-200 dark:border-gray-700 pb-2">
                    <span class="flex h-8 w-8 items-center justify-center rounded-full bg-yellow-100 text-yellow-600"><x-heroicon-m-clipboard-document-list class="h-5 w-5" /></span>
                    <h2 class="text-xl font-bold text-gray-800 dark:text-white">PARA PREPARAR</h2>
                    <span class="rounded-full bg-gray-100 dark:bg-gray-800 px-2.5 py-0.5 text-xs font-medium text-gray-800 dark:text-gray-300">{{ $this->ordersToProcess->count() }}</span>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    @forelse($this->ordersToProcess as $order)
                        @php
                            $priorityClass = match($order->priority) {
                                3 => 'ring-2 ring-red-500 bg-red-50 dark:bg-red-900/20', 
                                2 => 'ring-2 ring-orange-400 bg-orange-50 dark:bg-orange-900/10',
                                default => 'ring-1 ring-gray-200 bg-white dark:bg-gray-800 dark:ring-gray-700',
                            };

                            $priorityBadge = match($order->priority) {
                                3 => '<span class="animate-pulse bg-red-600 text-white text-[10px] font-black px-2 py-1 rounded uppercase tracking-wider shadow-sm">🔥 URGENTE</span>',
                                2 => '<span class="bg-orange-500 text-white text-[10px] font-bold px-2 py-1 rounded uppercase shadow-sm">⚡ ALTA</span>',
                                default => '<span class="bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 text-[10px] font-bold px-2 py-1 rounded uppercase">NORMAL</span>',
                            };
                        @endphp

                        <div wire:click="selectOrder({{ $order->id }})" class="group cursor-pointer relative overflow-hidden rounded-xl p-4 shadow-sm transition-all hover:shadow-md hover:scale-[1.02] {{ $priorityClass }}">
                            <div class="flex justify-between items-start mb-2">
                                <div>
                                    <h3 class="text-2xl font-black text-gray-900 dark:text-white">
                                        #{{ $order->id }}
                                        @if($order->parent_id)
                                            <span class="text-sm font-bold text-purple-700 bg-purple-100 px-2 py-0.5 rounded-full ml-2 align-middle border border-purple-200">HIJO DE #{{ $order->parent_id }}</span>
                                        @endif
                                    </h3>
                                    <p class="font-bold text-lg text-gray-700 dark:text-gray-300 truncate leading-tight">{{ $order->client->name ?? 'S/N' }}</p>
                                </div>
                                {!! $priorityBadge !!}
                            </div>
                            <div class="mt-3 flex flex-wrap gap-2">
                                <span class="inline-flex items-center px-2 py-1 rounded text-xs font-bold bg-blue-100 dark:bg-blue-900/40 text-blue-800 dark:text-blue-300 border border-blue-200 dark:border-blue-800">
                                    <x-heroicon-m-map-pin class="w-3 h-3 mr-1"/> {{ $order->client->locality->name ?? 'Sin Loc' }}
                                </span>
                            </div>
                            <div class="mt-2 text-xs text-gray-400 dark:text-gray-500 flex justify-between pt-2 border-t border-gray-200/50 dark:border-gray-700">
                                <span>{{ $order->items->count() }} ítems</span>
                                <span>{{ $order->order_date->format('d/m/Y') }}</span>
                            </div>
                        </div>
                    @empty 
                        <div class="col-span-full py-12 text-center">
                            <div class="flex justify-center mb-4"><div class="p-4 bg-gray-50 dark:bg-gray-800 rounded-full"><x-heroicon-o-check-circle class="w-12 h-12 text-gray-300 dark:text-gray-600"/></div></div>
                            <h3 class="text-lg font-medium text-gray-900 dark:text-white">Todo listo por hoy</h3>
                            <p class="text-gray-500 dark:text-gray-400">No hay pedidos pendientes de preparación.</p>
                        </div> 
                    @endforelse
                </div>
            </div>

            {{-- LISTA DE PREPARADOS --}}
            @if($this->ordersReady->count() > 0)
                <div class="mb-12">
                    <div class="flex items-center gap-3 mb-4 border-b border-gray-200 dark:border-gray-700 pb-2">
                        <span class="flex h-8 w-8 items-center justify-center rounded-full bg-green-100 text-green-600 dark:bg-green-900/30 dark:text-green-500"><x-heroicon-m-check-badge class="h-5 w-5" /></span>
                        <h2 class="text-xl font-bold text-gray-800 dark:text-white">PREPARADOS / LISTOS</h2>
                        <span class="rounded-full bg-gray-100 dark:bg-gray-800 px-2.5 py-0.5 text-xs font-medium text-gray-800 dark:text-gray-300">{{ $this->ordersReady->count() }}</span>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 opacity-80 hover:opacity-100 transition-opacity">
                        @foreach($this->ordersReady as $order)
                            <div wire:click="selectOrder({{ $order->id }})" class="cursor-pointer rounded-xl bg-white dark:bg-gray-800 p-4 shadow-sm ring-1 ring-gray-200 dark:ring-gray-700 transition-all hover:ring-green-500">
                                <div class="flex justify-between items-start mb-2">
                                    <div>
                                        <h3 class="text-lg font-bold text-gray-600 dark:text-gray-300">
                                            #{{ $order->id }}
                                            @if($order->parent_id)
                                                <span class="text-[10px] font-bold text-purple-600 bg-purple-100 px-1.5 py-0.5 rounded-full ml-1">HIJO #{{ $order->parent_id }}</span>
                                            @endif
                                        </h3>
                                        <p class="font-medium text-gray-600 dark:text-gray-400 truncate">{{ $order->client->name ?? 'S/N' }}</p>
                                    </div>
                                    <span class="bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-300 text-[10px] font-bold px-2 py-1 rounded uppercase flex items-center gap-1"><x-heroicon-m-check class="w-3 h-3"/> LISTO</span>
                                </div>
                                <div class="mt-2 text-xs text-gray-500">{{ $order->client->locality->name ?? '-' }}</div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- LISTA DE CANCELADOS A DESARMAR --}}
            @if($this->ordersToDisassemble->count() > 0)
                <div class="mb-12">
                    <div class="flex items-center gap-3 mb-4 border-b border-red-200 dark:border-red-900/50 pb-2">
                        <span class="flex h-8 w-8 items-center justify-center rounded-full bg-red-100 text-red-600 dark:bg-red-900/30 dark:text-red-500"><x-heroicon-m-archive-box-x-mark class="h-5 w-5 animate-pulse" /></span>
                        <h2 class="text-xl font-black text-red-700 dark:text-red-400 uppercase">PARA DESARMAR (CANCELADOS)</h2>
                        <span class="rounded-full bg-red-100 dark:bg-red-900 px-2.5 py-0.5 text-xs font-bold text-red-800 dark:text-red-300">{{ $this->ordersToDisassemble->count() }}</span>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        @foreach($this->ordersToDisassemble as $order)
                            @php $totalToReturn = $order->items->sum('packed_quantity'); @endphp
                            <div class="relative overflow-hidden rounded-xl bg-red-50 dark:bg-red-950/20 p-4 shadow-sm ring-2 ring-red-400 dark:ring-red-900">
                                <div class="flex justify-between items-start mb-2">
                                    <div>
                                        <h3 class="text-xl font-black text-red-900 dark:text-red-400">
                                            #{{ $order->id }} 
                                            @if($order->parent_id)
                                                <span class="text-sm font-bold text-purple-700 bg-purple-100 px-2 py-0.5 rounded-full mx-1">HIJO DE #{{ $order->parent_id }}</span>
                                            @endif
                                            <span class="text-sm font-normal">({{ $order->client->name }})</span>
                                        </h3>
                                        <p class="font-bold text-red-700 dark:text-red-300 mt-1">Devolver al estante: {{ $totalToReturn }} prendas</p>
                                    </div>
                                    <span class="bg-red-600 text-white text-[10px] font-black px-2 py-1 rounded uppercase flex items-center gap-1"><x-heroicon-m-x-circle class="w-3 h-3"/> ANULADO</span>
                                </div>
                                <div class="mt-4 flex justify-end">
                                    <x-filament::button wire:click="markAsDisassembled({{ $order->id }})" color="danger" size="sm" icon="heroicon-m-check">Confirmar Caja Vaciada</x-filament::button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

        @else
            {{-- VISTA MATRIZ ACTIVA --}}
            <div class="space-y-4">
                <div class="sticky top-4 z-50 bg-white dark:bg-gray-900 p-3 rounded-lg shadow-md border border-gray-200 dark:border-gray-700 flex justify-between items-center">
                    <div class="flex items-center gap-3 overflow-hidden">
                        <button wire:click="resetOrder" class="p-2 bg-gray-100 dark:bg-gray-800 rounded-full hover:bg-gray-200 dark:text-white shrink-0"><x-heroicon-m-arrow-left class="w-5 h-5"/></button>
                        <div class="truncate">
                            <h2 class="text-lg font-bold dark:text-white truncate">{{ $this->activeOrder->client->name }}</h2>
                            <p class="text-xs text-gray-500 font-bold">
                                #{{ $this->activeOrder->id }} 
                                @if($this->activeOrder->parent_id)
                                    <span class="text-purple-600 bg-purple-100 px-1.5 py-0.5 rounded-md ml-1 border border-purple-200 uppercase">Parte de #{{ $this->activeOrder->parent_id }}</span>
                                @endif
                            </p>
                        </div>
                    </div>
                    <div class="flex gap-2 shrink-0">
                        <x-filament::button 
                            color="success" size="md" icon="heroicon-m-check"
                            @click="
                                let pendientes = document.querySelectorAll('.st-pending').length;
                                if(pendientes > 0) {
                                    if(confirm('Tenés ' + pendientes + ' talles vacíos sin marcar. Se guardarán como 0 prendas. ¿Confirmar armado?')) {
                                        $wire.finalizeOrder();
                                    }
                                } else {
                                    $wire.finalizeOrder();
                                }
                            "
                        >
                            Finalizar Armado
                        </x-filament::button>
                    </div>
                </div>

                <div class="space-y-6">
                    @foreach($this->matrixData as $group)
                        <div class="bg-white dark:bg-gray-900 rounded-lg shadow border border-gray-300 dark:border-gray-700 overflow-hidden">
                            <div class="bg-gray-100 dark:bg-gray-800 px-4 py-2 border-b dark:border-gray-700 flex justify-between items-center">
                                <div class="flex items-center gap-2 overflow-hidden"><span class="font-bold text-sm text-gray-900 dark:text-white truncate uppercase">{{ $group['article_name'] }}</span></div>
                                <span class="font-mono bg-white dark:bg-gray-700 text-xs px-2 py-1 rounded border dark:border-gray-600 dark:text-gray-300">{{ $group['article_code'] }}</span>
                            </div>
                            <div class="overflow-x-auto p-2">
                                <table class="w-auto text-xs border-collapse mx-auto text-center">
                                    <thead>
                                        <tr class="border-b border-gray-200 dark:border-gray-700">
                                            <th class="p-2 w-24 text-left font-medium sticky left-0 bg-white dark:bg-gray-900 z-10">Variante</th>
                                            @foreach($group['sizes'] as $size) <th class="p-2 w-16 text-center font-bold">{{ $size['name'] }}</th> @endforeach
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($group['colors'] as $color)
                                            <tr>
                                                <td class="p-2 text-left sticky left-0 bg-white dark:bg-gray-900 z-10 border-r border-gray-100 dark:border-gray-700">
                                                    <div class="flex items-center gap-2">
                                                        <div class="w-3 h-3 rounded-full border" style="background-color: {{ $color['hex'] }}"></div>
                                                        <span class="font-medium truncate w-20 uppercase">{{ $color['name'] }}</span>
                                                    </div>
                                                </td>
                                                @foreach($group['sizes'] as $size)
                                                    <td class="p-1 text-center border-l dark:border-gray-800">
                                                        @php $itemData = $group['grid']['c_'.$color['id']]['s_'.$size['id']] ?? null; @endphp
                                                        
                                                        @if($itemData)
                                                            @php
                                                                $val = $packedQuantities[$itemData['id']] ?? null; 
                                                                $req = (int) $itemData['original_req']; 
                                                            @endphp
                                                            <div class="flex justify-center relative" x-data="{ current: @js($val), req: {{ $req }} }">
                                                                <input type="number" 
                                                                       placeholder="{{ $req }}" 
                                                                       wire:model.live.debounce.500ms="packedQuantities.{{ $itemData['id'] }}" 
                                                                       x-model="current"
                                                                       :class="{
                                                                           'st-pending': current === '' || current === null,
                                                                           'st-ok': current !== '' && current !== null && parseInt(current) === req,
                                                                           'st-diff': current !== '' && current !== null && parseInt(current) !== req
                                                                       }"
                                                                       class="sq-input">
                                                            </div>
                                                        @else
                                                            @php
                                                                $extraKey = $group['article_id'] . '_' . $color['id'] . '_' . $size['id'];
                                                                $qtyExtra = $extraQuantities[$extraKey] ?? null;
                                                            @endphp
                                                            <div class="flex justify-center relative" x-data="{ current: @js($qtyExtra) }">
                                                                <input type="number" 
                                                                       placeholder="-" 
                                                                       wire:model.live.debounce.500ms="extraQuantities.{{ $extraKey }}" 
                                                                       x-model="current"
                                                                       :class="current !== '' && current !== null && parseInt(current) > 0 ? 'st-new' : 'st-extra'"
                                                                       class="sq-input">
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

                {{-- EL CAMPO DE OBSERVACIONES AL FINAL DE LA MATRIZ --}}
                <div class="mt-6 bg-white dark:bg-gray-900 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-4">
                    <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2 flex items-center gap-2">
                        <x-heroicon-m-chat-bubble-bottom-center-text class="w-5 h-5 text-gray-400"/> 
                        Observaciones del Armador (Opcional)
                    </label>
                    <textarea 
                        wire:model.live="observations" 
                        class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm" 
                        rows="3" 
                        placeholder="Ej: Le mandé una bombacha blanca talle 44 en vez de la crema porque no había stock..."
                    ></textarea>
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>