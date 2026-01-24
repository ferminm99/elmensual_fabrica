<x-filament-panels::page>
    <div class="space-y-8">
        
        {{-- LISTADO DE PENDIENTES --}}
        @if(!$selectedOrderId)
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                @forelse($this->pendingOrders as $order)
                    <div class="relative group rounded-2xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-6 shadow-sm hover:shadow-xl hover:border-amber-500 transition-all duration-300">
                        <div class="flex justify-between items-start mb-6">
                            <div>
                                <span class="text-[10px] font-black uppercase tracking-[0.2em] text-amber-500">Logística El Mensual</span>
                                <h3 class="text-3xl font-black text-gray-900 dark:text-white tracking-tighter">#{{ $order->id }}</h3>
                            </div>
                            <div class="px-2 py-1 rounded bg-amber-500/10 text-amber-500 text-[10px] font-bold">PARA ARMAR</div>
                        </div>
                        
                        <div class="space-y-1 mb-8">
                            <p class="text-xs text-gray-400 font-medium">CLIENTE</p>
                            <p class="text-lg font-bold text-gray-800 dark:text-gray-100 truncate">{{ $order->client->name }}</p>
                        </div>

                        <x-filament::button 
                            wire:click="selectOrder({{ $order->id }})" 
                            color="warning"
                            icon="heroicon-m-play"
                            class="w-full py-4 text-lg font-black italic shadow-lg shadow-amber-500/20"
                        >
                            EMPEZAR ARMADO
                        </x-filament::button>
                    </div>
                @empty
                    <div class="col-span-full py-24 text-center border-2 border-dashed border-gray-200 dark:border-gray-800 rounded-[2rem] bg-gray-50/50 dark:bg-gray-900/20">
                        <x-heroicon-o-archive-box-x-mark class="w-20 h-20 text-gray-200 dark:text-gray-700 mx-auto mb-6" />
                        <h2 class="text-3xl font-black text-gray-300 dark:text-gray-700 uppercase italic tracking-tighter">Depósito Vacío</h2>
                        <p class="text-gray-400 dark:text-gray-600 mt-2 font-medium">No hay pedidos pendientes con estado "Para Armar".</p>
                    </div>
                @endforelse
            </div>
        @endif

        {{-- MATRIZ DE ARMADO --}}
        @if($selectedOrderId)
            @php $active = \App\Models\Order::with('client')->find($selectedOrderId); @endphp
            
            <div class="flex items-center justify-between bg-white dark:bg-gray-950 p-4 rounded-2xl border border-gray-200 dark:border-gray-800 shadow-sm">
                <div class="flex items-center gap-6">
                    <button wire:click="$set('selectedOrderId', null)" class="p-3 rounded-full hover:bg-gray-100 dark:hover:bg-gray-800 text-gray-400 transition">
                        <x-heroicon-m-arrow-left class="w-6 h-6" />
                    </button>
                    <div>
                        <h2 class="text-2xl font-black text-gray-900 dark:text-white italic tracking-tight uppercase">Pedido #{{ $selectedOrderId }}</h2>
                        <p class="text-primary-500 font-bold">{{ $active->client->name }}</p>
                    </div>
                </div>
            </div>

            <x-filament::section class="p-0 overflow-hidden rounded-2xl border-none shadow-2xl">
                <table class="w-full text-left">
                    <thead class="bg-gray-50 dark:bg-white/5 border-b border-gray-100 dark:border-white/5">
                        <tr class="text-[10px] font-black text-gray-400 dark:text-gray-500 uppercase tracking-widest">
                            <th class="p-6">Descripción</th>
                            <th class="p-6 text-center">Talle</th>
                            <th class="p-6 text-center">Pedido</th>
                            <th class="p-6 text-center w-48 bg-amber-500/5">Confirmado</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                        @foreach($active->items as $item)
                            <tr class="hover:bg-gray-50/50 dark:hover:bg-white/5 transition-colors">
                                <td class="p-6">
                                    <div class="font-bold text-gray-900 dark:text-white">{{ $item->article->name }}</div>
                                    <div class="text-[11px] font-bold text-gray-400 uppercase tracking-tighter">{{ $item->color->name }}</div>
                                </td>
                                <td class="p-6 text-center">
                                    <span class="px-3 py-1 rounded-lg bg-gray-100 dark:bg-gray-800 font-black text-gray-600 dark:text-gray-400">{{ $item->size->name }}</span>
                                </td>
                                <td class="p-6 text-center font-mono text-4xl text-gray-200 dark:text-gray-800 tracking-tighter">
                                    {{ $item->quantity }}
                                </td>
                                <td class="p-6 bg-amber-500/5">
                                    <x-filament::input 
                                        type="number" 
                                        wire:model="packedQuantities.{{ $item->id }}"
                                        class="text-center font-black text-4xl text-amber-500 !bg-transparent border-none focus:ring-0"
                                    />
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                <div class="p-8 bg-gray-50 dark:bg-white/5 flex justify-end items-center gap-6">
                    <p class="text-xs font-bold text-gray-400 dark:text-gray-500">¿Todo listo? Verificá antes de cerrar.</p>
                    <x-filament::button 
                        size="xl" 
                        color="success" 
                        wire:click="confirmPacking"
                        class="px-20 py-6 text-2xl font-black italic shadow-xl shadow-emerald-500/20 hover:scale-105 transition-transform"
                    >
                        CONFIRMAR CARGA
                    </x-filament::button>
                </div>
            </x-filament::section>
        @endif

    </div>
</x-filament-panels::page>