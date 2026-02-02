<div class="space-y-10 text-white">
    @php
        $state = $this->data['article_groups'] ?? [];
        $childState = $this->data['child_groups'] ?? [];
        $existingChildren = $this->data['existing_children'] ?? [];
        $status = $this->data['status'] ?? 'draft';
        
        $isEditable = $status === 'draft'; 
        $isStandby = $status === 'standby';
        $isPicking = $status === 'processing';
        
        // Bloqueo visual si ya está avanzado
        $isLocked = in_array($status, ['assembled', 'checked', 'dispatched', 'delivered', 'paid']);
        $isSuperiorDisabled = !$isEditable && !$isPicking;
    @endphp

    {{-- 1. RESUMEN CONSOLIDADO --}}
    @if(!$isEditable && $status !== 'cancelled')
        <div class="p-4 bg-slate-900 border border-sky-500/30 rounded-xl shadow-inner">
            <div class="flex items-center gap-3 text-sky-400 mb-2">
                <x-heroicon-m-information-circle class="w-5 h-5"/>
                <span class="text-xs font-black uppercase tracking-widest italic">Modo Lectura / Armado</span>
            </div>
            <div class="text-[10px] text-slate-400 font-medium italic uppercase tracking-tighter">
                Visualizando Pedido Original. <span class="text-emerald-400">Verde: Armado completo</span> | <span class="text-amber-400">Naranja: Parcial</span>
            </div>
        </div>
    @endif

    {{-- CAPA 1: MATRIZ ORIGINAL (EL PADRE) --}}
    <div class="space-y-4 {{ $isSuperiorDisabled ? 'opacity-50 grayscale' : '' }} transition-all">
        <div class="flex items-center justify-between border-l-4 border-sky-500 pl-4">
            <div class="flex items-center gap-3">
                <h3 class="text-sky-500 font-black text-[10px] uppercase tracking-widest italic">Pedido Principal</h3>
                @if($isStandby)
                    <span class="text-[9px] bg-sky-500/20 text-sky-400 px-2 py-0.5 rounded border border-sky-500/30 font-bold animate-pulse">EDICIÓN HABILITADA</span>
                @endif
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
                    @if($status === 'draft')
                        <button type="button" x-on:click="$wire.mountFormComponentAction('article_groups', 'removeArticle', { groupKey: '{{ $groupKey }}' })" class="ml-auto text-slate-500 hover:text-red-500 transition-colors">
                            <x-heroicon-m-trash class="w-4 h-4"/>
                        </button>
                    @endif
                </div>

                <table class="w-full border-collapse">
                    <thead>
                        <tr class="bg-slate-900/50 border-b border-slate-700 text-slate-500 font-black uppercase text-[9px]">
                            <th class="p-2 text-left px-6 w-48 italic tracking-tighter">Variante</th>
                            <th class="p-2 w-10"></th>
                            @foreach($sizes as $size)
                                <th class="p-2 text-center border-l border-slate-700/30 w-24 tracking-tighter text-slate-300">{{ $size->name }}</th>
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
                                    @if($isEditable)
                                        <button type="button" x-on:click="$wire.mountFormComponentAction('article_groups', 'fillRow', { uuid: '{{ $uuid }}', groupKey: '{{ $groupKey }}' })" class="text-sky-500 p-1 active:scale-90 transition-transform">
                                            <x-heroicon-m-bolt class="w-4 h-4 mx-auto"/>
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
                                            @if(!$isEditable && $qVal > 0)
                                                <span class="text-[8px] font-black opacity-40 uppercase tracking-tighter">P:{{ $qVal }}</span>
                                            @endif

                                            @if($isEditable)
                                                <input type="number" wire:model.defer="data.article_groups.{{ $groupKey }}.matrix.{{ $uuid }}.qty_{{ $size->id }}"
                                                    class="w-full bg-transparent border-none text-center text-sm font-black p-0 focus:ring-0 text-sky-400" placeholder="0">
                                            @else
                                                <input type="number" wire:model.defer="data.article_groups.{{ $groupKey }}.matrix.{{ $uuid }}.packed_{{ $size->id }}"
                                                    @disabled(!$isPicking)
                                                    class="w-full bg-transparent border-none text-center text-sm font-black p-0 focus:ring-0 {{ !$isPicking ? 'cursor-not-allowed text-slate-500' : 'text-emerald-400' }}" 
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

    {{-- CAPA 2: PEDIDOS HIJOS YA GUARDADOS (SOLO LECTURA) --}}
    @if(!empty($existingChildren))
        <div class="space-y-6 pt-10 border-t-2 border-slate-800">
            <div class="flex items-center gap-2 border-l-4 border-slate-600 pl-4">
                <h3 class="text-slate-500 font-black text-xs uppercase tracking-widest italic">Historial de Hijos Guardados</h3>
            </div>

            @foreach($existingChildren as $child)
                <div class="p-4 bg-slate-900/40 rounded-2xl border border-slate-800 relative opacity-75 hover:opacity-100 transition-opacity">
                    <div class="flex justify-between items-center mb-4">
                         <span class="bg-slate-700 text-slate-300 px-3 py-1 rounded-full text-[10px] font-black italic">PEDIDO HIJO #{{ $child['id'] }}</span>
                         <a href="{{ \App\Filament\Resources\OrderResource::getUrl('edit', ['record' => $child['id']]) }}" class="text-[10px] text-sky-400 font-bold hover:underline uppercase">Ir al detalle →</a>
                    </div>
                    
                    @foreach($child['groups'] as $g)
                        @php
                            $cArt = \App\Models\Article::find($g['article_id']);
                            $cSizes = \App\Models\Sku::where('article_id', $g['article_id'])->join('sizes', 'skus.size_id', '=', 'sizes.id')->select('sizes.id', 'sizes.name')->distinct()->orderBy('sizes.id')->get();
                        @endphp
                        <div class="mb-4 last:mb-0 border border-slate-700/50 rounded-lg overflow-hidden">
                            <div class="bg-slate-800/50 p-2 px-4 text-[10px] font-bold text-slate-400 uppercase flex gap-4">
                                <span class="text-sky-500">{{ $cArt->code }}</span> {{ $cArt->name }}
                            </div>
                            <table class="w-full text-[11px]">
                                <tr class="bg-slate-900/80 text-slate-500 uppercase font-black text-[9px]">
                                    <td class="p-2 px-4">Color</td>
                                    @foreach($cSizes as $s) <td class="p-2 text-center border-l border-slate-800">{{ $s->name }}</td> @endforeach
                                </tr>
                                @foreach($g['matrix'] as $row)
                                <tr class="border-t border-slate-800">
                                    <td class="p-2 px-4 flex items-center gap-2">
                                        <div class="w-2 h-2 rounded-full" style="background-color: {{ $row['color_hex'] }}"></div>
                                        {{ $row['color_name'] }}
                                    </td>
                                    @foreach($cSizes as $s)
                                        <td class="p-2 text-center border-l border-slate-800 font-bold {{ (int)($row['qty_'.$s->id] ?? 0) < 0 ? 'text-red-400' : 'text-slate-300' }}">
                                            {{ $row['qty_'.$s->id] ?? 0 }}
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

    {{-- CAPA 3: FÁBRICA DE NUEVOS HIJOS (SÓLO STANDBY) --}}
    @if($isStandby)
        <div class="space-y-4 pt-10 border-t-2 border-dashed border-emerald-500/30">
            <div class="flex items-center gap-2 border-l-4 border-emerald-500 pl-4">
                <div>
                    <h3 class="text-emerald-500 font-black text-xs uppercase tracking-widest italic text-white">Nuevos Adicionales (Generará Pedido Hijo)</h3>
                    <p class="text-[9px] text-emerald-600 font-bold uppercase">Cargue cantidades positivas para sumar o negativas para restar del total.</p>
                </div>
            </div>

            @foreach($childState as $cKey => $cGroup)
                @php
                    $cArt = \App\Models\Article::find($cGroup['article_id']);
                    if(!$cArt) continue;
                    $cSizes = \App\Models\Sku::where('article_id', $cGroup['article_id'])
                        ->join('sizes', 'skus.size_id', '=', 'sizes.id')
                        ->select('sizes.id', 'sizes.name')->distinct()->orderBy('sizes.id')->get();
                @endphp
                <div class="bg-emerald-950/20 rounded-xl border border-emerald-500/30 overflow-hidden mb-6 shadow-2xl">
                    <div class="p-3 bg-emerald-900/20 flex justify-between items-center px-6 border-b border-emerald-500/20">
                        <div class="flex items-center gap-3">
                            <span class="bg-emerald-600 text-white px-2 py-0.5 rounded text-[10px] font-black italic tracking-tighter">{{ $cArt->code }}</span>
                            <span class="text-emerald-200 font-bold text-xs uppercase italic">{{ $cArt->name }}</span>
                        </div>
                        <button type="button" x-on:click="$wire.mountFormComponentAction('article_groups', 'removeChildGroup', { key: '{{ $cKey }}' })" class="text-red-400 hover:text-red-300">
                            <x-heroicon-m-trash class="w-4 h-4" />
                        </button>
                    </div>
                    <table class="w-full border-collapse">
                        <thead>
                            <tr class="bg-emerald-900/10 border-b border-emerald-500/10 text-emerald-600/60 font-black uppercase text-[9px]">
                                <th class="p-2 text-left px-6 w-48 italic tracking-tighter">Variante Color</th>
                                <th class="p-2 w-10"></th>
                                @foreach($cSizes as $s)
                                    <th class="p-2 text-center border-l border-emerald-500/10 w-24 tracking-tighter text-emerald-100">{{ $s->name }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($cGroup['matrix'] as $cUuid => $cRow)
                                <tr class="border-b border-emerald-500/10 hover:bg-emerald-500/5 h-12 transition-colors">
                                    <td class="p-1 px-6 flex items-center gap-3">
                                        <div class="w-3 h-3 rounded-full shadow-sm border border-white/10" style="background-color: {{ $cRow['color_hex'] }}"></div>
                                        <span class="text-[10px] font-bold text-emerald-100 uppercase truncate">{{ $cRow['color_name'] }}</span>
                                    </td>
                                    <td class="p-1 text-center">
                                        <button type="button" x-on:click="$wire.mountFormComponentAction('article_groups', 'fillChildRow', { uuid: '{{ $cUuid }}', key: '{{ $cKey }}' })" class="text-emerald-400 hover:text-emerald-200 active:scale-90 transition-transform">
                                            <x-heroicon-m-bolt class="w-4 h-4 mx-auto" />
                                        </button>
                                    </td>
                                    @foreach($cSizes as $s)
                                        <td class="p-0 border-l border-emerald-500/10">
                                            <input type="number" 
                                                wire:model.defer="data.child_groups.{{ $cKey }}.matrix.{{ $cUuid }}.qty_{{ $s->id }}" 
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