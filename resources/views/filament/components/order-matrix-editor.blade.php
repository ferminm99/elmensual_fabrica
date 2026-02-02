<style>
    /* Quitar flechitas de los inputs */
    input[type=number]::-webkit-inner-spin-button, 
    input[type=number]::-webkit-outer-spin-button { -webkit-appearance: none; margin: 0; }
    input[type=number] { -moz-appearance: textfield; }

    /* Estilos de Celda Unificados */
    .matrix-sq-cell { 
        width: 44px; height: 34px; border-radius: 6px; 
        display: flex; align-items: center; justify-content: center; 
        font-weight: 800; font-size: 13px; margin: auto; 
    }
    .matrix-input-clean { 
        width: 100%; height: 100%; text-align: center; 
        background: transparent !important; border: none !important; 
        padding: 0; font-weight: 900; outline: none !important;
        box-shadow: none !important;
        color: inherit;
    }

    /* Colores de Estado Adaptativos (Picker Style) */
    .st-pending { background-color: #fef3c7; border: 2px solid #fbbf24; color: #92400e !important; }
    .dark .st-pending { background-color: #451a03; border: 2px solid #b45309; color: #fbbf24 !important; }

    .st-ok { background-color: #dcfce7; border: 2px solid #22c55e; color: #15803d !important; }
    .dark .st-ok { background-color: #064e3b; border: 2px solid #10b981; color: #34d399 !important; }

    .st-diff { background-color: #fee2e2; border: 2px solid #ef4444; color: #b91c1c !important; }
    .dark .st-diff { background-color: #450a0a; border: 2px solid #dc2626; color: #f87171 !important; }

    .st-neutral { background-color: #f3f4f6; border: 1px solid #d1d5db; color: #374151 !important; }
    .dark .st-neutral { background-color: #1e293b; border: 1px solid #334155; color: #94a3b8 !important; }

    .st-edit { background-color: #eff6ff; border: 2px solid #3b82f6; color: #1d4ed8 !important; }
    .dark .st-edit { background-color: #172554; border: 2px solid #2563eb; color: #60a5fa !important; }
</style>

<div class="space-y-10">
    @php
        $state = $this->data['article_groups'] ?? [];
        $childState = $this->data['child_groups'] ?? [];
        $existingChildren = $this->data['existing_children'] ?? [];
        $status = $this->data['status'] ?? 'draft';
        
        $isEditable = $status === 'draft'; 
        $isStandby = $status === 'standby';
    @endphp

    {{-- CAPA 1: PEDIDO PRINCIPAL --}}
    <div class="space-y-4">
        <div class="flex items-center gap-3 border-l-4 border-primary-500 pl-4">
            <h3 class="text-sm font-black uppercase tracking-widest italic text-gray-950 dark:text-white">
                Pedido Principal
            </h3>
            @if($isStandby)
                <span class="text-[10px] px-2 py-0.5 rounded bg-primary-500/10 text-primary-600 dark:text-primary-400 border border-primary-500/20 font-bold uppercase italic">Vista Previa</span>
            @endif
        </div>

        @foreach($state as $groupKey => $group)
            @php
                $article = \App\Models\Article::find($group['article_id']);
                if(!$article) continue;
                $sizes = \App\Models\Sku::where('article_id', $group['article_id'])->join('sizes', 'skus.size_id', '=', 'sizes.id')->select('sizes.id', 'sizes.name')->distinct()->orderBy('sizes.id')->get();
            @endphp

            <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-white/10 bg-white dark:bg-gray-900 shadow-sm mb-6">
                <div class="flex items-center gap-4 border-b border-gray-200 dark:border-white/10 bg-gray-50 dark:bg-white/5 p-3 px-6">
                    <span class="rounded bg-primary-600 px-2 py-0.5 text-[10px] font-black italic text-white shadow-sm">{{ $article->code }}</span>
                    <span class="text-sm font-bold text-gray-800 dark:text-gray-100 uppercase tracking-tight">{{ $article->name }}</span>
                </div>

                <table class="w-full border-collapse">
                    <thead>
                        <tr class="border-b border-gray-200 dark:border-white/10 bg-gray-50/50 dark:bg-white/5 text-[9px] font-black uppercase text-gray-500 dark:text-gray-400">
                            <th class="px-6 py-2 text-left">Variante</th>
                            @foreach($sizes as $size)
                                <th class="w-20 border-l border-gray-200 dark:border-white/5 px-2 py-2 text-center">{{ $size->name }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-white/5">
                        @foreach($group['matrix'] as $uuid => $row)
                            <tr>
                                <td class="px-6 py-3">
                                    <div class="flex items-center gap-3">
                                        <div class="h-3 w-3 rounded-full border border-gray-300 dark:border-white/20 shadow-sm" style="background-color: {{ $row['color_hex'] }}"></div>
                                        <span class="text-[10px] font-bold text-gray-700 dark:text-gray-300 uppercase truncate">{{ $row['color_name'] }}</span>
                                    </div>
                                </td>
                                @foreach($sizes as $size)
                                    @php
                                        $qVal = (int)($row["qty_{$size->id}"] ?? 0);
                                        $pVal = (int)($row["packed_{$size->id}"] ?? 0);
                                        
                                        if($qVal == 0) $st = 'st-neutral opacity-20';
                                        elseif($pVal == 0) $st = 'st-pending';
                                        elseif($pVal >= $qVal) $st = 'st-ok';
                                        else $st = 'st-diff';
                                    @endphp
                                    <td class="border-l border-gray-200 dark:border-white/5 p-2">
                                        @if($isEditable)
                                            <div class="matrix-sq-cell st-edit shadow-inner">
                                                <input type="number" wire:model.defer="data.article_groups.{{ $groupKey }}.matrix.{{ $uuid }}.qty_{{ $size->id }}" class="matrix-input-clean">
                                            </div>
                                        @else
                                            <div class="matrix-sq-cell {{ $st }} flex-col leading-none shadow-sm">
                                                <span class="text-sm font-black">{{ $qVal }}</span>
                                                @if($pVal > 0) <span class="text-[7px] font-bold uppercase opacity-80">A:{{ $pVal }}</span> @endif
                                            </div>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endforeach
    </div>

    {{-- CAPA 2: HIJOS GUARDADOS --}}
    @if(!empty($existingChildren))
        <div class="space-y-6 pt-10 border-t-2 border-gray-200 dark:border-gray-800">
            <h3 class="pl-4 border-l-4 border-gray-400 dark:border-gray-600 text-[10px] font-black uppercase italic text-gray-500 dark:text-gray-400">
                Hijos Relacionados
            </h3>
            @foreach($existingChildren as $child)
                <div class="rounded-2xl border border-gray-200 dark:border-white/10 bg-gray-50 dark:bg-white/5 p-4 shadow-sm mb-6">
                    <div class="flex justify-between items-center mb-4 px-2">
                         <span class="rounded-full border border-gray-300 dark:border-white/20 bg-gray-200 dark:bg-white/10 px-3 py-1 text-[10px] font-black italic text-gray-800 dark:text-white uppercase tracking-widest shadow-sm">HIJO #{{ $child['id'] }}</span>
                         <a href="{{ \App\Filament\Resources\OrderResource::getUrl('edit', ['record' => $child['id']]) }}" class="text-[10px] font-black text-primary-600 dark:text-primary-400 uppercase hover:underline tracking-tighter">Ver Detalle →</a>
                    </div>
                    @foreach($child['groups'] as $g)
                        @php 
                            $cArt = \App\Models\Article::find($g['article_id']); 
                            $cSizes = \App\Models\Sku::where('article_id', $g['article_id'])->join('sizes', 'skus.size_id', '=', 'sizes.id')->select('sizes.id', 'sizes.name')->distinct()->orderBy('sizes.id')->get();
                        @endphp
                        <div class="mb-4 overflow-hidden rounded-lg border border-gray-200 dark:border-white/10 bg-white dark:bg-gray-950 shadow-inner">
                            <div class="flex gap-4 border-b border-gray-200 dark:border-white/10 bg-gray-50 dark:bg-white/5 p-2 px-4 text-[10px] font-black text-gray-800 dark:text-gray-200 uppercase">
                                <span class="text-primary-600 dark:text-primary-400 italic">{{ $cArt?->code }}</span> {{ $cArt?->name }}
                            </div>
                            <table class="w-full text-center border-collapse">
                                <tr class="bg-gray-50/50 dark:bg-white/5 text-[9px] font-black uppercase text-gray-400 dark:text-gray-500 border-b border-gray-100 dark:border-white/5">
                                    <td class="px-4 py-2 text-left italic">Color</td>
                                    @foreach($cSizes as $s) <td class="w-16 border-l border-gray-100 dark:border-white/5 px-2 py-2">{{ $s->name }}</td> @endforeach
                                </tr>
                                @foreach($g['matrix'] as $row)
                                <tr class="border-b border-gray-50 dark:border-white/5 last:border-0 h-10">
                                    <td class="px-4 py-2 flex items-center gap-2 text-[10px] font-bold text-gray-500 dark:text-gray-400 uppercase">
                                        <div class="h-2 w-2 rounded-full border border-gray-200 dark:border-white/20 shadow-sm" style="background-color: {{ $row['color_hex'] }}"></div>
                                        {{ $row['color_name'] }}
                                    </td>
                                    @foreach($cSizes as $s)
                                        @php $cV = (int)($row['qty_'.$s->id] ?? 0); @endphp
                                        <td class="border-l border-gray-100 dark:border-white/5">
                                            @if($cV != 0)
                                                <div class="matrix-sq-cell !w-10 !h-7 mx-auto {{ $cV < 0 ? 'st-diff' : 'st-ok' }} shadow-sm">
                                                    {{ $cV }}
                                                </div>
                                            @else
                                                <span class="text-gray-300 dark:text-gray-700 font-black">-</span>
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                                @endforeach
                            </table>
                        </div>
                    @endforeach
                </div>
            @endforeach
        </div>
    @endif

    {{-- CAPA 3: GENERADOR DE HIJOS (STANDBY) --}}
    @if($isStandby)
        <div class="pt-10 border-t-2 border-dashed border-emerald-500/30">
            <h3 class="mb-6 border-l-4 border-emerald-500 pl-4 text-xs font-black uppercase italic text-emerald-600 dark:text-emerald-400">Generar Ajuste de Carga</h3>
            @foreach($childState as $cKey => $cGroup)
                @php 
                    $cArt = \App\Models\Article::find($cGroup['article_id']); 
                    $cSizes = \App\Models\Sku::where('article_id', $cGroup['article_id'])->join('sizes', 'skus.size_id', '=', 'sizes.id')->select('sizes.id', 'sizes.name')->distinct()->orderBy('sizes.id')->get();
                @endphp
                <div class="mb-6 overflow-hidden rounded-xl border border-emerald-200 dark:border-emerald-500/30 bg-emerald-50/30 dark:bg-emerald-500/5 shadow-sm">
                    <div class="flex justify-between items-center border-b border-emerald-200 dark:border-emerald-500/20 bg-emerald-100/50 dark:bg-emerald-500/10 p-3 px-6 text-xs font-bold uppercase">
                        <div class="flex items-center gap-4 text-emerald-900 dark:text-emerald-100"> 
                            <span class="rounded bg-emerald-600 px-2 py-0.5 text-[10px] text-white shadow-sm italic font-black">{{ $cArt?->code }}</span>
                            {{ $cArt?->name }} 
                        </div>
                        <button type="button" x-on:click="$wire.mountFormComponentAction('article_groups', 'removeChildGroup', { key: '{{ $cKey }}' })" class="text-red-500 hover:scale-110 transition-transform"><x-heroicon-m-trash class="w-5 h-5" /></button>
                    </div>
                    <table class="w-full border-collapse">
                        <tr class="bg-emerald-50/50 dark:bg-emerald-500/5 text-[9px] font-black uppercase text-emerald-600 dark:text-emerald-400 border-b border-emerald-200/20">
                            <th class="px-6 py-2 text-left italic">Variante</th>
                            @foreach($cSizes as $s) <th class="w-20 border-l border-emerald-200 dark:border-emerald-500/10 px-2 py-2 text-gray-700 dark:text-gray-300">{{ $s->name }}</th> @endforeach
                        </tr>
                        @foreach($cGroup['matrix'] as $cUuid => $cRow)
                            <tr class="border-t border-emerald-200/20 dark:border-emerald-500/10 h-12">
                                <td class="px-6 py-3 flex items-center gap-3 text-[10px] font-bold text-emerald-800 dark:text-emerald-300/80 uppercase">
                                    <div class="h-3 w-3 rounded-full border border-emerald-300 dark:border-emerald-700 shadow-sm" style="background-color: {{ $cRow['color_hex'] }}"></div> {{ $cRow['color_name'] }}
                                </td>
                                @foreach($cSizes as $s)
                                    <td class="border-l border-emerald-200 dark:border-emerald-500/10 p-2">
                                        <div class="matrix-sq-cell bg-white dark:bg-gray-800 border-emerald-400 dark:border-emerald-500/40 border-2 shadow-inner">
                                            <input type="number" wire:model.defer="data.child_groups.{{ $cKey }}.matrix.{{ $cUuid }}.qty_{{ $s->id }}" 
                                                class="matrix-input-clean !text-emerald-700 dark:!text-emerald-400 p-0 focus:ring-0" placeholder="0">
                                        </div>
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </table>
                </div>
            @endforeach
        </div>
    @endif
</div>