<div class="space-y-10">
    @php
        $state = $this->data['article_groups'] ?? [];
        $childState = $this->data['child_groups'] ?? [];
        $status = $this->data['status'] ?? 'draft';
        $isDraft = $status === 'draft';
        $isStandby = $status === 'standby';
        $isPicking = $status === 'processing';
        $isLocked = in_array($status, ['assembled', 'checked', 'dispatched', 'delivered', 'paid']);
    @endphp

    <div class="space-y-4">
        <h3 class="text-slate-400 font-black text-[10px] uppercase border-l-4 border-sky-500 pl-4 tracking-widest">Pedido Principal</h3>
        @foreach($state as $groupKey => $group)
            @php
                $article = \App\Models\Article::find($group['article_id']);
                $sizes = \App\Models\Sku::where('article_id', $group['article_id'])->join('sizes', 'skus.size_id', '=', 'sizes.id')
                    ->select('sizes.id', 'sizes.name')->distinct()->orderBy('sizes.id')->get();
            @endphp
            <div class="bg-[#0f172a] rounded-xl border border-slate-700 overflow-hidden mb-6">
                <div class="p-3 bg-slate-800/80 flex items-center px-6 gap-4 border-b border-slate-700">
                    <span class="bg-sky-500 text-white px-2 py-0.5 rounded text-[10px] font-black italic">{{ $article?->code }}</span>
                    <span class="text-slate-200 font-bold text-xs uppercase">{{ $article?->name }}</span>
                    @if($isDraft)
                        <button type="button" x-on:click="$wire.mountFormComponentAction('article_groups', 'removeArticle', { groupKey: '{{ $groupKey }}' })" class="ml-auto text-slate-500 hover:text-red-500"><x-heroicon-m-trash class="w-4 h-4" /></button>
                    @endif
                </div>
                <table class="w-full border-collapse">
                    <thead>
                        <tr class="bg-slate-900/50 text-slate-500 font-black uppercase text-[9px]">
                            <th class="p-2 text-left px-6 w-48">Color</th>
                            <th class="p-2 w-10"></th>
                            @foreach($sizes as $size) <th class="p-2 text-center border-l border-slate-700/30 w-24">{{ $size->name }}</th> @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($group['matrix'] as $uuid => $row)
                            <tr class="border-b border-slate-800/50 h-14">
                                <td class="p-1 px-6 flex items-center gap-3">
                                    <div class="w-3 h-3 rounded-full border border-white/10" style="background-color: {{ $row['color_hex'] }}"></div>
                                    <span class="text-[10px] font-bold text-slate-300 uppercase truncate">{{ $row['color_name'] }}</span>
                                </td>
                                <td class="p-1 text-center">
                                    @if($isDraft)
                                        <button type="button" x-on:click="$wire.mountFormComponentAction('article_groups', 'fillRow', { uuid: '{{ $uuid }}', groupKey: '{{ $groupKey }}' })" class="text-sky-500"><x-heroicon-m-bolt class="w-4 h-4" /></button>
                                    @endif
                                </td>
                                @foreach($sizes as $size)
                                    @php
                                        $qVal = (int)($row["qty_{$size->id}"] ?? 0);
                                        $pVal = (int)($row["packed_{$size->id}"] ?? 0);
                                        $color = 'text-slate-700';
                                        if($qVal > 0) {
                                            if($pVal >= $qVal) $color = 'bg-emerald-500/10 text-emerald-400 font-black';
                                            elseif($pVal > 0) $color = 'bg-amber-500/10 text-amber-400 font-black';
                                            else $color = 'text-slate-400 font-bold';
                                        }
                                    @endphp
                                    <td class="p-0 border-l border-slate-700/30 {{ $color }}">
                                        <div class="flex flex-col items-center justify-center h-14">
                                            @if(!$isDraft && $qVal > 0) <span class="text-[8px] font-black opacity-40 uppercase">P:{{ $qVal }}</span> @endif
                                            @if($isDraft)
                                                <input type="number" wire:model.defer="data.article_groups.{{ $groupKey }}.matrix.{{ $uuid }}.qty_{{ $size->id }}" class="w-full bg-transparent border-none text-center text-sm font-black p-0 focus:ring-0 text-sky-400" placeholder="0">
                                            @else
                                                <input type="number" wire:model.defer="data.article_groups.{{ $groupKey }}.matrix.{{ $uuid }}.packed_{{ $size->id }}" @disabled($isLocked || $isStandby) class="w-full bg-transparent border-none text-center text-sm font-black p-0 focus:ring-0" placeholder="0">
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

    @if($isStandby)
        <div class="space-y-4 pt-10 border-t-2 border-dashed border-emerald-500/30">
            <h3 class="text-emerald-500 font-black text-[10px] uppercase border-l-4 border-emerald-500 pl-4 tracking-widest">Adicionales (Pedido Hijo)</h3>
            @foreach($childState as $cKey => $cGroup)
                @php $cArt = \App\Models\Article::find($cGroup['article_id']); @endphp
                <div class="bg-emerald-950/10 rounded-xl border border-emerald-500/30 overflow-hidden mb-6 shadow-2xl">
                    <div class="p-3 bg-emerald-900/20 flex justify-between items-center px-6">
                        <span class="text-emerald-500 font-black text-[10px] italic uppercase tracking-tighter">{{ $cArt?->code }} - {{ $cArt?->name }}</span>
                        <button type="button" x-on:click="$wire.mountFormComponentAction('article_groups', 'removeChildGroup', { key: '{{ $cKey }}' })" class="text-red-400"><x-heroicon-m-trash class="w-4 h-4" /></button>
                    </div>
                    <table class="w-full border-collapse">
                        <tbody>
                            @foreach($cGroup['matrix'] as $cUuid => $cRow)
                                <tr class="border-b border-emerald-500/10 h-12">
                                    <td class="p-1 px-6 flex items-center gap-3 w-48"><div class="w-3 h-3 rounded-full" style="background-color: {{ $cRow['color_hex'] }}"></div><span class="text-[10px] font-bold text-emerald-100 uppercase truncate">{{ $cRow['color_name'] }}</span></td>
                                    <td class="w-full"></td> {{-- Espaciador --}}
                                    @foreach(\App\Models\Sku::where('article_id', $cGroup['article_id'])->join('sizes', 'skus.size_id', '=', 'sizes.id')->select('sizes.id')->distinct()->get() as $s)
                                        <td class="p-0 border-l border-emerald-500/10"><input type="number" wire:model.defer="data.child_groups.{{ $cKey }}.matrix.{{ $cUuid }}.qty_{{ $s->id }}" class="w-full h-12 bg-transparent border-none text-center text-sm font-black text-emerald-400 p-0 focus:ring-0" placeholder="0"></td>
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