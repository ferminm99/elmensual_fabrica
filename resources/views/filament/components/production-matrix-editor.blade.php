<style>
    input[type=number]::-webkit-inner-spin-button, input[type=number]::-webkit-outer-spin-button { -webkit-appearance: none; margin: 0; }
    input[type=number] { -moz-appearance: textfield; }
    .matrix-sq-cell { width: 44px; height: 34px; border-radius: 6px; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 13px; margin: auto; transition: all 0.2s ease-in-out; }
    .matrix-input-clean { width: 100%; height: 100%; text-align: center; background: transparent !important; border: none !important; padding: 0; font-weight: 900; outline: none !important; color: inherit; }
    .matrix-input-clean::placeholder { color: #9ca3af; font-weight: 500; opacity: 0.6; }
    .dark .matrix-input-clean::placeholder { color: #64748b; }
    
    .st-edit { background-color: #eff6ff; border: 2px solid #3b82f6; color: #1d4ed8 !important; }
    .dark .st-edit { background-color: #172554; border: 2px solid #2563eb; color: #60a5fa !important; }
    
    .st-alert { background-color: #fee2e2 !important; border: 2px solid #ef4444 !important; color: #b91c1c !important; }
    .dark .st-alert { background-color: #450a0a !important; border: 2px solid #dc2626 !important; color: #f87171 !important; }
</style>

<div class="space-y-6">
    @php
        $groups = $getState() ?? [];
        $statePath = $getStatePath(); 
        $originalGroups = $original_groups ?? []; // Rescatamos los números puros de la Base de Datos
    @endphp

    <div class="mb-4 p-4 rounded-lg bg-blue-50 dark:bg-blue-900/30 border border-blue-200 dark:border-blue-800">
        <h3 class="text-lg font-black text-blue-800 dark:text-blue-300">Auditoría de Ingreso</h3>
        <p class="text-sm text-blue-600 dark:text-blue-400 mt-1">
            Revisá las cantidades esperadas. Si ingresó una cantidad distinta, <strong>modificá el número</strong> (se pondrá en rojo y verás el número original arriba). Si borrás la celda, el sistema te obligará a cargar un 0 como mínimo.
        </p>
    </div>

    @foreach($groups as $groupKey => $group)
        @php
            $article = \App\Models\Article::find($group['article_id']);
            $sizes = \App\Models\Sku::where('article_id', $group['article_id'])->join('sizes', 'skus.size_id', '=', 'sizes.id')->select('sizes.id', 'sizes.name')->distinct()->orderBy('sizes.id')->get();
            $totalArt = 0;
            foreach($group['matrix'] as $row) { foreach($sizes as $s) $totalArt += (int)($row["qty_{$s->id}"] ?? 0); }
        @endphp

        <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-white/10 bg-white dark:bg-gray-900 shadow-sm" x-data="{ isSelected: true }">
            <div class="flex items-center gap-4 border-b border-gray-200 dark:border-white/10 bg-gray-50 dark:bg-white/5 p-3 px-6 cursor-pointer hover:bg-gray-100 dark:hover:bg-white/10" @click="isSelected = !isSelected">
                <x-heroicon-m-chevron-down class="w-4 h-4 text-gray-400 transition-transform" x-bind:class="!isSelected ? '-rotate-90' : ''" />
                <span class="text-sm font-bold text-gray-800 dark:text-gray-100 uppercase tracking-tight">{{ $article->code }} - {{ $article->name }}</span>
                <div class="ml-auto flex items-center gap-2">
                    <span class="text-[10px] font-black bg-primary-500/10 text-primary-600 dark:text-primary-400 px-2 py-1 rounded-full uppercase">Esperadas: {{ $totalArt }}</span>
                </div>
            </div>

            <div x-show="isSelected" x-collapse>
                <table class="w-full border-collapse pb-4">
                    <thead>
                        <tr class="bg-gray-50/50 dark:bg-white/5 text-[9px] font-black uppercase text-gray-500 dark:text-gray-400 border-b dark:border-white/5">
                            <th class="px-6 py-2 text-left">Variante</th>
                            <th class="w-10 italic text-primary-500 text-center">Bolt</th>
                            @foreach($sizes as $size)
                                <th class="w-20 border-l dark:border-white/5 px-2 py-2 text-center">{{ $size->name }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($group['matrix'] as $uuid => $row)
                            <tr class="border-b dark:border-white/5 last:border-0">
                                <td class="px-6 py-4 flex items-center gap-3">
                                    <div class="h-3 w-3 rounded-full border border-gray-300 dark:border-white/20 shadow-sm" style="background-color: {{ $row['color_hex'] }}"></div>
                                    <span class="text-[10px] font-bold text-gray-700 dark:text-gray-300 uppercase truncate">{{ $row['color_name'] }}</span>
                                </td>
                                <td class="p-0 text-center border-l dark:border-white/5">
                                    <button type="button" @click="
                                        let tr = $el.closest('tr');
                                        let inps = tr.querySelectorAll('input[type=number]');
                                        let val = Array.from(inps).find(i => i.value > 0)?.value;
                                        if(val) {
                                            inps.forEach(i => { 
                                                i.value = val; 
                                                i.dispatchEvent(new Event('input', { bubbles: true })); 
                                            });
                                        }
                                    " class="text-primary-500 p-1 hover:scale-110 transition-transform">
                                        <x-heroicon-m-bolt class="w-4 h-4 mx-auto"/>
                                    </button>
                                </td>
                                @foreach($sizes as $size)
                                    @php 
                                        $qV = (int)($row["qty_{$size->id}"] ?? 0); 
                                        // Extraemos el número original directo de la base de datos inyectada
                                        $origQv = (int)($originalGroups[$groupKey]['matrix'][$uuid]["qty_{$size->id}"] ?? 0);
                                    @endphp
                                    {{-- Agregamos pt-5 y relative al TD para hacerle lugar al numerito arriba --}}
                                    <td class="border-l dark:border-white/5 p-2 pt-5 relative align-bottom" x-data="{ original: '{{ $origQv }}', current: '{{ $qV }}' }">
                                        
                                        {{-- NÚMERO ORIGINAL FLOTANDO ARRIBA DE LA CAJA --}}
                                        <template x-if="current != original">
                                            <div class="absolute top-1 left-0 w-full text-center text-[10px] font-black text-red-600 dark:text-red-400" x-text="original"></div>
                                        </template>

                                        <div class="matrix-sq-cell shadow-inner" 
                                             :class="(current == original) ? 'st-edit' : 'st-alert'">
                                            
                                            <input type="number" 
                                                   x-on:input="current = $event.target.value"
                                                   wire:model.live.debounce.500ms="{{ $statePath }}.{{ $groupKey }}.matrix.{{ $uuid }}.qty_{{ $size->id }}" 
                                                   placeholder="{{ $origQv }}"
                                                   min="0"
                                                   {{ $origQv > 0 ? 'required' : '' }}
                                                   class="matrix-input-clean">
                                        </div>
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <div class="h-2"></div>
            </div>
        </div>
    @endforeach
</div>