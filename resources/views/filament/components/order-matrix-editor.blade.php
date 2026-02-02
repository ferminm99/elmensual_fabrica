<div class="space-y-10">
    @php
        $state = $this->data['article_groups'] ?? [];
        $childState = $this->data['child_groups'] ?? [];
        $status = $this->data['status'] ?? 'draft';
        
        $isDraft = $status === 'draft';
        $isStandby = $status === 'standby';
        $isPicking = $status === 'processing';
        
        // Bloqueo total si el pedido ya está en una etapa logística avanzada
        $isLocked = in_array($status, ['assembled', 'checked', 'dispatched', 'delivered', 'paid']);
        
        // La matriz superior se ve normal en Draft y Picking, se opaca en Standby/avanzados
        $isSuperiorDisabled = !$isDraft && !$isPicking;
    @endphp

    {{-- 1. RESUMEN CONSOLIDADO --}}
    @if(!$isDraft)
        <div class="p-4 bg-slate-900 border border-sky-500/30 rounded-xl shadow-inner">
            <div class="flex items-center gap-3 text-sky-400 mb-2">
                <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M18.375 2.25c-1.035 0-1.875.84-1.875 1.875v15.75c0 1.035.84 1.875 1.875 1.875h.75c1.035 0 1.875-.84 1.875-1.875V4.125c0-1.035-.84-1.875-1.875-1.875h-.75zM9.75 8.625c0-1.035.84-1.875 1.875-1.875h.75c1.035 0 1.875.84 1.875 1.875v11.25c0 1.035-.84 1.875-1.875 1.875h-.75a1.875 1.875 0 01-1.875-1.875V8.625zM3 13.125c0-1.035.84-1.875 1.875-1.875h.75c1.035 0 1.875.84 1.875 1.875v6.75c0 1.035-.84 1.875-1.875 1.875h-.75A1.875 1.875 0 013 19.875v-6.75z" /></svg>
                <span class="text-xs font-black uppercase tracking-widest italic text-white">Referencia de Carga</span>
            </div>
            <div class="text-[10px] text-slate-400 font-medium italic uppercase tracking-tighter">
                Visualizando Pedido Original. <span class="text-emerald-400">Verde: Armado completo</span> | <span class="text-amber-400">Naranja: Parcial</span>
            </div>
        </div>
    @endif

    {{-- CAPA 1: MATRIZ ORIGINAL (EL PADRE) --}}
    <div class="space-y-4 {{ $isSuperiorDisabled ? 'opacity-50 grayscale' : '' }} transition-all">
        <div class="flex items-center justify-between border-l-4 border-sky-500 pl-4">
            <div>
                <h3 class="text-slate-400 font-black text-[10px] uppercase tracking-widest text-white italic">Pedido Principal</h3>
            </div>
            @if($isPicking)
                <span class="text-[10px] bg-amber-500 text-black px-3 py-1 rounded-full font-black italic animate-pulse">MODO ARMADO ACTIVO</span>
            @endif
        </div>

        @foreach($state as $groupKey => $group)
            @php
                $article = \App\Models\Article::find($group['article_id']);
                if(!$article) continue;
                $sizes = \App\Models\Sku::where('article_id', $group['article_id'])
                    ->join('sizes', 'skus.size_id', '=', 'sizes.id')
                    ->select('sizes.id', 'sizes.name')->distinct()->orderBy('sizes.id')->get();
            @endphp

            <div class="bg-[#0f172a] rounded-xl border border-slate-700 overflow-hidden shadow-2xl mb-6">
                <div class="p-3 bg-slate-800/80 border-b border-slate-700 flex items-center px-6 gap-4">
                    <span class="bg-sky-500 text-white px-2 py-0.5 rounded text-[10px] font-black italic tracking-tighter">{{ $article->code }}</span>
                    <span class="text-slate-200 font-bold text-xs uppercase tracking-tight">{{ $article->name }}</span>
                    @if($isDraft)
                        <button type="button" x-on:click="$wire.mountFormComponentAction('article_groups', 'removeArticle', { groupKey: '{{ $groupKey }}' })" class="ml-auto text-slate-500 hover:text-red-500 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                        </button>
                    @endif
                </div>

                <table class="w-full border-collapse">
                    <thead>
                        <tr class="bg-slate-900/50 border-b border-slate-700 text-slate-500 font-black uppercase text-[9px]">
                            <th class="p-2 text-left px-6 w-48 italic tracking-tighter">Variante</th>
                            <th class="p-2 w-10"></th>
                            @foreach($sizes as $size)
                                <th class="p-2 text-center border-l border-slate-700/30 w-24 tracking-tighter">{{ $size->name }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($group['matrix'] as $uuid => $row)
                            <tr class="border-b border-slate-800/50 hover:bg-slate-800/20 transition-colors h-14">
                                <td class="p-1 px-6 flex items-center gap-3">
                                    <div class="w-3 h-3 rounded-full border border-white/10 shadow-sm" style="background-color: {{ $row['color_hex'] }}"></div>
                                    <span class="text-[10px] font-bold text-slate-300 uppercase truncate">{{ $row['color_name'] }}</span>
                                </td>
                                <td class="p-1 text-center">
                                    @if($isDraft)
                                        <button type="button" x-on:click="$wire.mountFormComponentAction('article_groups', 'fillRow', { uuid: '{{ $uuid }}', groupKey: '{{ $groupKey }}' })" class="text-sky-500 p-1 active:scale-90 transition-transform">
                                            <svg class="w-4 h-4 mx-auto" fill="currentColor" viewBox="0 0 20 20"><path d="M11.983 1.907a.75.75 0 00-1.292-.657l-8.5 9.5A.75.75 0 002.75 12h6.572l-1.305 6.093a.75.75 0 001.292.657l8.5-9.5A.75.75 0 0017.25 8h-6.572l1.305-6.093z" /></svg>
                                        </button>
                                    @endif
                                </td>
                                @foreach($sizes as $size)
                                    @php
                                        $qVal = (int)($row["qty_{$size->id}"] ?? 0);
                                        $pVal = (int)($row["packed_{$size->id}"] ?? 0);
                                        
                                        $cellColor = 'text-slate-700';
                                        if($qVal > 0){
                                            if($pVal >= $qVal) $cellColor = 'bg-emerald-500/10 text-emerald-400 font-black';
                                            elseif($pVal > 0) $cellColor = 'bg-amber-500/10 text-amber-400 font-black';
                                            else $cellColor = 'text-slate-400 font-bold';
                                        }
                                    @endphp
                                    <td class="p-0 border-l border-slate-700/30 {{ $cellColor }} transition-colors">
                                        <div class="flex flex-col items-center justify-center h-14 gap-0.5">
                                            @if(!$isDraft && $qVal > 0)
                                                <span class="text-[8px] font-black opacity-40 uppercase tracking-tighter">P:{{ $qVal }}</span>
                                            @endif

                                            @if($isDraft)
                                                <input type="number" wire:model.defer="data.article_groups.{{ $groupKey }}.matrix.{{ $uuid }}.qty_{{ $size->id }}"
                                                    class="w-full bg-transparent border-none text-center text-sm font-black p-0 focus:ring-0 text-sky-400" placeholder="0">
                                            @else
                                                <input type="number" wire:model.defer="data.article_groups.{{ $groupKey }}.matrix.{{ $uuid }}.packed_{{ $size->id }}"
                                                    @disabled(!$isPicking)
                                                    class="w-full bg-transparent border-none text-center text-sm font-black p-0 focus:ring-0 {{ !$isPicking ? 'cursor-not-allowed' : 'text-emerald-400' }}" 
                                                    placeholder="0">
                                            @endif
                                        </div>
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endforeach
    </div>

    {{-- CAPA 2: FÁBRICA DE HIJOS (SÓLO STANDBY) --}}
    @if($isStandby)
        <div class="space-y-4 pt-10 border-t-2 border-dashed border-emerald-500/30">
            <div class="flex items-center gap-2 border-l-4 border-emerald-500 pl-4">
                <div>
                    <h3 class="text-emerald-500 font-black text-xs uppercase tracking-widest italic">Adicionales (Pedido Hijo)</h3>
                </div>
            </div>

            @foreach($childState as $cKey => $cGroup)
                @php
                    $cArticle = \App\Models\Article::find($cGroup['article_id']);
                    if(!$cArticle) continue;
                    $cSizes = \App\Models\Sku::where('article_id', $cGroup['article_id'])
                        ->join('sizes', 'skus.size_id', '=', 'sizes.id')
                        ->select('sizes.id', 'sizes.name')->distinct()->orderBy('sizes.id')->get();
                @endphp
                <div class="bg-emerald-950/10 rounded-xl border border-emerald-500/30 overflow-hidden shadow-2xl mb-6">
                    <div class="p-3 bg-emerald-900/20 border-b border-emerald-500/30 flex justify-between items-center px-6">
                        <div class="flex items-center gap-3">
                            <span class="bg-emerald-500 text-white px-2 py-0.5 rounded text-[10px] font-black italic tracking-tighter">{{ $cArticle->code }}</span>
                            <span class="text-emerald-200 font-bold text-xs uppercase italic">{{ $cArticle->name }}</span>
                        </div>
                        <button type="button" x-on:click="$wire.mountFormComponentAction('article_groups', 'removeChildGroup', { key: '{{ $cKey }}' })" class="text-emerald-700 hover:text-red-400 p-1">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                        </button>
                    </div>
                    <table class="w-full border-collapse">
                        <tbody>
                            @foreach($cGroup['matrix'] as $cUuid => $cRow)
                                <tr class="border-b border-emerald-500/10 h-12">
                                    <td class="p-1 px-6 flex items-center gap-3 w-48">
                                        <div class="w-3 h-3 rounded-full shadow-sm border border-white/10" style="background-color: {{ $cRow['color_hex'] }}"></div>
                                        <span class="text-[10px] font-bold text-emerald-100 uppercase truncate">{{ $cRow['color_name'] }}</span>
                                    </td>
                                    <td class="p-1 text-center">
                                        <button type="button" x-on:click="$wire.mountFormComponentAction('article_groups', 'fillChildRow', { uuid: '{{ $cUuid }}', key: '{{ $cKey }}' })" class="text-emerald-400 hover:text-emerald-200">
                                            <svg class="w-4 h-4 mx-auto" fill="currentColor" viewBox="0 0 20 20"><path d="M11.983 1.907a.75.75 0 00-1.292-.657l-8.5 9.5A.75.75 0 002.75 12h6.572l-1.305 6.093a.75.75 0 001.292.657l8.5-9.5A.75.75 0 0017.25 8h-6.572l1.305-6.093z" /></svg>
                                        </button>
                                    </td>
                                    @foreach($cSizes as $s)
                                        <td class="p-0 border-l border-emerald-500/10">
                                            <input type="number" wire:model.defer="data.child_groups.{{ $cKey }}.matrix.{{ $cUuid }}.qty_{{ $s->id }}"
                                                class="w-full h-12 bg-transparent border-none text-center text-sm font-black text-emerald-400 p-0 focus:ring-0 focus:bg-emerald-500/10 transition-colors" placeholder="0">
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endforeach
        </div>
    @endif
</div>