<style>
    input[type=number]::-webkit-inner-spin-button, input[type=number]::-webkit-outer-spin-button { -webkit-appearance: none; margin: 0; }
    input[type=number] { -moz-appearance: textfield; }
    .matrix-sq-cell { width: 44px; height: 34px; border-radius: 6px; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 13px; margin: auto; }
    .matrix-input-clean { width: 100%; height: 100%; text-align: center; background: transparent !important; border: none !important; padding: 0; font-weight: 900; outline: none !important; color: inherit; }
    .st-pending { background-color: #fef3c7; border: 2px solid #fbbf24; color: #92400e !important; }
    .dark .st-pending { background-color: #451a03; border: 2px solid #b45309; color: #fbbf24 !important; }
    .st-ok { background-color: #dcfce7; border: 2px solid #22c55e; color: #15803d !important; }
    .dark .st-ok { background-color: #064e3b; border: 2px solid #10b981; color: #34d399 !important; }
    .st-diff { background-color: #fee2e2; border: 2px solid #ef4444; color: #b91c1c !important; }
    .dark .st-diff { background-color: #450a0a; border: 2px solid #dc2626; color: #f87171 !important; }
    .st-edit { background-color: #eff6ff; border: 2px solid #3b82f6; color: #1d4ed8 !important; }
    .dark .st-edit { background-color: #172554; border: 2px solid #2563eb; color: #60a5fa !important; }
</style>

<div class="space-y-10">
    @php
        // CORRECCIÓN CLAVE: Extraer valor del Enum
        $rawStatus = $this->data['status'] ?? 'draft';
        $status = $rawStatus instanceof \BackedEnum ? $rawStatus->value : $rawStatus;
        
        // Si es null (recién creado), asumimos draft
        if(is_null($status)) $status = 'draft';

        $isEditable = $status === 'draft';
        $isStandby = $status === 'standby';
        
        $sections = [
            ['title' => 'Pedido Principal', 'data' => $this->data['article_groups'] ?? [], 'editable' => $isEditable, 'key' => 'article_groups', 'color' => 'primary'],
            ['title' => 'Nuevos Adicionales', 'data' => $this->data['child_groups'] ?? [], 'editable' => $isStandby, 'key' => 'child_groups', 'color' => 'emerald']
        ];
    @endphp

    @foreach($sections as $section)
        @if(!empty($section['data']))
            <div class="space-y-4">
                <div class="flex items-center gap-3 border-l-4 border-{{ $section['color'] }}-500 pl-4">
                    <h3 class="text-sm font-black uppercase tracking-widest italic text-gray-950 dark:text-white">{{ $section['title'] }}</h3>
                </div>

                @foreach($section['data'] as $groupKey => $group)
                    @php
                        $article = \App\Models\Article::find($group['article_id']);
                        $sizes = \App\Models\Sku::where('article_id', $group['article_id'])->join('sizes', 'skus.size_id', '=', 'sizes.id')->select('sizes.id', 'sizes.name')->distinct()->orderBy('sizes.id')->get();
                        $totalArt = 0;
                        foreach($group['matrix'] as $row) { foreach($sizes as $s) $totalArt += (int)($row["qty_{$s->id}"] ?? 0); }
                    @endphp

                    <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-white/10 bg-white dark:bg-gray-900 shadow-sm mb-4" 
                         x-data="{ isSelected: true }">
                        
                        <div class="flex items-center gap-4 border-b border-gray-200 dark:border-white/10 bg-gray-50 dark:bg-white/5 p-3 px-6 cursor-pointer hover:bg-gray-100 dark:hover:bg-white/10 transition-colors" 
                             @click="isSelected = !isSelected">
                            <x-heroicon-m-chevron-down class="w-4 h-4 text-gray-400 transition-transform" x-bind:class="!isSelected ? '-rotate-90' : ''" />
                            <span class="rounded bg-{{ $section['color'] }}-600 px-2 py-0.5 text-[10px] font-black italic text-white shadow-sm">{{ $article->code }}</span>
                            <span class="text-sm font-bold text-gray-800 dark:text-gray-100 uppercase tracking-tight">{{ $article->name }}</span>
                            
                            <div class="ml-auto flex items-center gap-2">
                                <span class="text-[10px] font-black bg-{{ $section['color'] }}-500/10 text-{{ $section['color'] }}-600 dark:text-{{ $section['color'] }}-400 px-2 py-1 rounded-full uppercase">Total: {{ $totalArt }}</span>
                                @if($section['editable'] && $section['key'] === 'article_groups')
                                    <button type="button" x-on:click.stop="$wire.mountFormComponentAction('article_groups', 'removeArticle', { groupKey: '{{ $groupKey }}' })" class="text-gray-400 hover:text-red-500"><x-heroicon-m-trash class="w-4 h-4"/></button>
                                @endif
                            </div>
                        </div>

                        <div x-show="isSelected" x-collapse>
                            <table class="w-full border-collapse">
                                <thead>
                                    <tr class="bg-gray-50/50 dark:bg-white/5 text-[9px] font-black uppercase text-gray-500 dark:text-gray-400 border-b dark:border-white/5">
                                        <th class="px-6 py-2 text-left">Variante</th>
                                        <th class="w-10 italic text-{{ $section['color'] }}-500 text-center">Bolt</th>
                                        @foreach($sizes as $size)
                                            <th class="w-20 border-l dark:border-white/5 px-2 py-2 text-center">{{ $size->name }}</th>
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($group['matrix'] as $uuid => $row)
                                        <tr class="border-b dark:border-white/5 last:border-0">
                                            <td class="px-6 py-3 flex items-center gap-3">
                                                <div class="h-3 w-3 rounded-full border border-gray-300 dark:border-white/20 shadow-sm" style="background-color: {{ $row['color_hex'] }}"></div>
                                                <span class="text-[10px] font-bold text-gray-700 dark:text-gray-300 uppercase truncate">{{ $row['color_name'] }}</span>
                                            </td>
                                            <td class="p-0 text-center border-l dark:border-white/5">
                                                @if($section['editable'])
                                                    <button type="button" x-on:click="$wire.mountFormComponentAction('article_groups', '{{ $section['key'] === 'article_groups' ? 'fillRow' : 'fillChildRow' }}', { uuid: '{{ $uuid }}', {{ $section['key'] === 'article_groups' ? 'groupKey' : 'key' }}: '{{ $groupKey }}' })" class="text-{{ $section['color'] }}-500 p-1 hover:scale-110 transition-transform"><x-heroicon-m-bolt class="w-4 h-4 mx-auto"/></button>
                                                @endif
                                            </td>
                                            @foreach($sizes as $size)
                                                <td class="border-l dark:border-white/5 p-2">
                                                    @php $qV = (int)($row["qty_{$size->id}"] ?? 0); $pV = (int)($row["packed_{$size->id}"] ?? 0); @endphp
                                                    <div class="matrix-sq-cell {{ $section['editable'] ? 'st-edit shadow-inner' : ($qV == 0 ? 'st-neutral opacity-20' : ($pV >= $qV ? 'st-ok shadow-sm' : ($pV > 0 ? 'st-diff shadow-sm' : 'st-pending shadow-sm'))) }}">
                                                        @if($section['editable'])
                                                            {{-- CORRECCIÓN: Usamos live.debounce para que se sienta fluido --}}
                                                            <input type="number" wire:model.live.debounce.500ms="data.{{ $section['key'] }}.{{ $groupKey }}.matrix.{{ $uuid }}.qty_{{ $size->id }}" class="matrix-input-clean">
                                                        @else
                                                            <div class="flex flex-col leading-none"><span class="text-sm font-black">{{ $qV }}</span>@if($pV > 0)<span class="text-[7px] font-bold uppercase opacity-80">A:{{ $pV }}</span>@endif</div>
                                                        @endif
                                                    </div>
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
        @endif
    @endforeach

    {{-- HIJOS YA GUARDADOS (SOLO LECTURA) --}}
    @if(!empty($this->data['existing_children']))
        <div class="space-y-6 pt-10 border-t-2 border-gray-200 dark:border-gray-800">
            <h3 class="pl-4 border-l-4 border-gray-400 dark:border-gray-600 text-[10px] font-black uppercase italic text-gray-500 dark:text-gray-400">Hijos Guardados</h3>
            @foreach($this->data['existing_children'] as $child)
                <div class="rounded-2xl border border-gray-200 dark:border-white/10 bg-gray-50 dark:bg-white/5 p-4 mb-6 shadow-sm" x-data="{ isSelected: false }">
                    <div class="flex justify-between items-center px-2 cursor-pointer" @click="isSelected = !isSelected">
                         <div class="flex items-center gap-2">
                             <x-heroicon-m-chevron-down class="w-4 h-4 text-gray-400 transition-transform" x-bind:class="!isSelected ? '-rotate-90' : ''" />
                             <span class="rounded-full border border-gray-300 dark:border-white/20 bg-gray-200 dark:bg-white/10 px-3 py-1 text-[10px] font-black text-gray-800 dark:text-white uppercase tracking-tighter shadow-sm">HIJO #{{ $child['id'] }}</span>
                         </div>
                         <a href="{{ \App\Filament\Resources\OrderResource::getUrl('edit', ['record' => $child['id']]) }}" class="text-[10px] font-black text-primary-600 dark:text-primary-400 uppercase hover:underline">Ver Detalle →</a>
                    </div>
                    <div x-show="isSelected" x-collapse class="mt-4 space-y-4">
                        @foreach($child['groups'] as $g)
                            @php $cArt = \App\Models\Article::find($g['article_id']); $cSizes = \App\Models\Sku::where('article_id', $g['article_id'])->join('sizes', 'skus.size_id', '=', 'sizes.id')->select('sizes.id', 'sizes.name')->distinct()->orderBy('sizes.id')->get(); @endphp
                            <div class="overflow-hidden rounded-lg border border-gray-200 dark:border-white/10 bg-white dark:bg-gray-950">
                                <div class="bg-gray-100 dark:bg-white/5 p-2 px-4 text-[10px] font-black text-gray-700 dark:text-gray-300 uppercase"><span class="text-primary-600 mr-2">{{ $cArt?->code }}</span> {{ $cArt?->name }}</div>
                                <table class="w-full text-center">
                                    <tr class="bg-gray-50/50 dark:bg-white/5 text-[9px] font-black uppercase text-gray-400 dark:text-gray-500 border-b dark:border-white/5">
                                        <td class="px-4 py-2 text-left italic">Color</td>
                                        @foreach($cSizes as $s) <td class="w-16 border-l dark:border-white/5 px-2 py-2">{{ $s->name }}</td> @endforeach
                                    </tr>
                                    @foreach($g['matrix'] as $row)
                                        <tr class="border-b border-gray-50 dark:border-white/5 last:border-0 h-10">
                                            <td class="px-4 py-2 flex items-center gap-2 text-[10px] font-bold text-gray-600 dark:text-gray-400 uppercase"><div class="h-2 w-2 rounded-full border border-gray-300 dark:border-white/20" style="background-color: {{ $row['color_hex'] }}"></div> {{ $row['color_name'] }}</td>
                                            @foreach($cSizes as $s)
                                                <td class="border-l border-gray-100 dark:border-white/5">
                                                    @php $cV = (int)($row['qty_'.$s->id] ?? 0); @endphp
                                                    @if($cV != 0) <div class="matrix-sq-cell !w-10 !h-7 mx-auto shadow-sm {{ $cV < 0 ? 'st-diff' : 'st-ok' }}">{{ $cV }}</div> @else <span class="text-gray-300 dark:text-gray-700">-</span> @endif
                                                </td>
                                            @endforeach
                                        </tr>
                                    @endforeach
                                </table>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>