@php
    $record = $getRecord();
    if (!$record) return;

    $allItems = collect($record->items);
    foreach($record->children()->where('status', '!=', \App\Enums\OrderStatus::Cancelled)->get() as $child) {
        $allItems = $allItems->concat($child->items);
    }
    
    $summary = $allItems->groupBy('sku_id')->map(function($group) {
        $first = $group->first();
        return [
            'article' => $first->sku->article->code,
            'name' => $first->sku->article->name,
            'color' => $first->color->name ?? 'N/C',
            'hex' => $first->color->hex_code ?? '#ccc',
            'size' => $first->sku->size->name ?? 'S/T',
            'total' => $group->sum('quantity'),
        ];
    });
@endphp

<div class="rounded-xl border border-gray-200 dark:border-white/10 overflow-hidden">
    <table class="w-full text-left divide-y divide-gray-200 dark:divide-white/5">
        <thead>
            <tr class="bg-gray-50 dark:bg-white/5">
                <th class="px-4 py-2 text-xxs font-bold uppercase dark:text-gray-400">Artículo</th>
                <th class="px-4 py-2 text-xxs font-bold uppercase dark:text-gray-400 text-center">Color/Talle</th>
                <th class="px-4 py-2 text-xxs font-bold uppercase dark:text-gray-400 text-right">Consolidado</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200 dark:divide-white/5 bg-white dark:bg-transparent">
            @foreach($summary as $item)
                <tr class="text-xs">
                    <td class="px-4 py-2 font-medium dark:text-gray-200">
                        {{ $item['article'] }} <span class="hidden md:inline">- {{ $item['name'] }}</span>
                    </td>
                    <td class="px-4 py-2 text-center">
                        <div class="flex items-center justify-center gap-2">
                            <div class="w-3 h-3 rounded-full border border-black/10" style="background-color: {{ $item['hex'] }}"></div>
                            <span class="font-bold dark:text-gray-300">{{ $item['size'] }}</span>
                        </div>
                    </td>
                    <td class="px-4 py-2 text-right font-black text-primary-600 dark:text-primary-400">
                        {{ $item['total'] }} u.
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>