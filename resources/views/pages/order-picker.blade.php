<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Buscador de Pedidos --}}
        <x-filament::section>
            <div class="flex gap-4 items-end">
                <x-filament::input.wrapper label="N° de Pedido">
                    <x-filament::input type="text" wire:model="orderId" placeholder="Ej: 1025" />
                </x-filament::input.wrapper>
                <x-filament::button wire:click="loadOrder({{ $orderId }})">
                    Cargar Pedido
                </x-filament::button>
            </div>
        </x-filament::section>

        @if($orderId)
        <x-filament::section header="Artículos a preparar">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="border-b">
                        <th class="p-2">Artículo / Color</th>
                        <th class="p-2">Talle</th>
                        <th class="p-2 text-center">Pedido</th>
                        <th class="p-2 text-center w-32">Confirmado</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach(\App\Models\Order::find($orderId)->items as $item)
                    <tr class="border-b hover:bg-gray-50">
                        <td class="p-2">
                            <span class="font-bold">{{ $item->article->name }}</span><br>
                            <span class="text-xs text-gray-500">{{ $item->color->name }}</span>
                        </td>
                        <td class="p-2">{{ $item->size->name }}</td>
                        <td class="p-2 text-center text-gray-400 font-mono">
                            {{ $item->quantity }}
                        </td>
                        <td class="p-2">
                            <x-filament::input 
                                type="number" 
                                wire:model="packedQuantities.{{ $item->id }}"
                                class="text-center font-bold text-primary-600"
                            />
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>

            <div class="mt-6 flex justify-end">
                <x-filament::button size="lg" color="success" wire:click="confirmPacking">
                    Confirmar y Generar Remito
                </x-filament::button>
            </div>
        </x-filament::section>
        @endif
    </div>
</x-filament-panels::page>