<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use App\Models\Order;
use App\Models\Sku;
use App\Enums\OrderStatus;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Filament\Notifications\Notification;
use Filament\Forms\Get;
use Filament\Forms;
use Illuminate\Support\HtmlString;

class EditOrder extends EditRecord
{
    protected static string $resource = OrderResource::class;

    protected function beforeFill(): void
    {
        $record = $this->getRecord();
        $userId = auth()->id();
        $ahora = now();

        $isLocked = $record->locked_at && $record->locked_at->diffInMinutes($ahora) < 2;

        if ($isLocked && $record->locked_by !== $userId) {
            $user = $record->lockedBy?->name ?? 'Un armador';
            Notification::make()
                ->danger()
                ->title("PEDIDO OCUPADO")
                ->body("{$user} lo tiene abierto. Evita cambios simultáneos.")
                ->persistent()
                ->send();
        } else {
            DB::table('orders')->where('id', $record->id)->update([
                'locked_by' => $userId,
                'locked_at' => $ahora,
            ]);
        }
    }

    protected function afterSave(): void
    {
        DB::table('orders')->where('id', $this->getRecord()->id)->update([
            'locked_by' => null,
            'locked_at' => null,
        ]);
    }

    protected function beforeSave(): void
    {
        $record = $this->getRecord();
        $isLocked = $record->locked_at && $record->locked_at->diffInMinutes(now()) < 2;
        
        if ($isLocked && $record->locked_by !== auth()->id()) {
            Notification::make()->danger()->title("Error al guardar")->body("El pedido está siendo editado por otro usuario.")->send();
            $this->halt(); 
        }
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $order = $this->getRecord();
        $items = $order->items()->with(['sku.size', 'color'])->get();
        $grouped = $items->groupBy('article_id');
        $groups = [];

        foreach ($grouped as $articleId => $articleItems) {
            $matrix = [];
            $groupedColor = $articleItems->groupBy('color_id');
            foreach ($groupedColor as $colorId => $colorItems) {
                $first = $colorItems->first();
                $row = [
                    'color_id' => $colorId,
                    'color_name' => $first->color?->name ?? 'S/N',
                    'color_hex' => $first->color?->hex_code ?? '#334155',
                ];
                foreach ($colorItems as $item) {
                    if($item->sku) {
                        $row["qty_{$item->sku->size_id}"] = $item->quantity;
                        $row["packed_{$item->sku->size_id}"] = $item->packed_quantity ?? 0;
                    }
                }
                $matrix[uniqid()] = $row;
            }
            $groups[uniqid()] = ['article_id' => $articleId, 'matrix' => $matrix];
        }
        $data['article_groups'] = $groups;

        $children = Order::where('parent_id', $order->id)->with(['items.sku.size', 'items.color'])->get();
        $childGroupsData = [];
        foreach ($children as $child) {
            $itemsChild = $child->items->groupBy('article_id');
            $groupsChild = [];
            foreach ($itemsChild as $articleId => $articleItems) {
                $matrix = [];
                foreach ($articleItems->groupBy('color_id') as $colorId => $colorItems) {
                    $first = $colorItems->first();
                    $row = [
                        'color_id' => $colorId,
                        'color_name' => $first->color?->name ?? 'S/N',
                        'color_hex' => $first->color?->hex_code ?? '#334155',
                    ];
                    foreach ($colorItems as $item) {
                        if($item->sku) $row["qty_{$item->sku->size_id}"] = $item->quantity;
                    }
                    $matrix[uniqid()] = $row;
                }
                $groupsChild[] = ['article_id' => $articleId, 'matrix' => $matrix, 'child_id' => $child->id];
            }
            $childGroupsData[] = ['id' => $child->id, 'groups' => $groupsChild];
        }
        $data['existing_children'] = $childGroupsData;

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            // BOTÓN FACTURAR
            \Filament\Actions\Action::make('facturar_pedido')
                ->label('Facturar')
                ->color('success')
                ->icon('heroicon-o-document-check')
                ->modalWidth('7xl') // Modal más ancho
                ->visible(fn (Order $record) => 
                    in_array($record->status instanceof \BackedEnum ? $record->status->value : $record->status, ['assembled', 'standby']) 
                    && !$record->invoices()->where('invoice_type', 'B')->exists()
                    && is_null($record->parent_id)
                )
                ->form(function (Order $record) {
                    // FIX DEFINITIVO: Traemos el Padre y TODOS los hijos con sus items
                    $orderIds = \App\Models\Order::where('id', $record->id)
                        ->orWhere('parent_id', $record->id)
                        ->pluck('id')
                        ->toArray();
                        
                    $itemsAgrupados = \App\Models\OrderItem::with('article')
                        ->whereIn('order_id', $orderIds)
                        ->get();
                        
                    $grouped = $itemsAgrupados->groupBy('article_id');
                    $tbody = '';
                    $totalCostoPedido = 0;

                    foreach ($grouped as $articleId => $items) {
                        // Sumamos la cantidad armada (o si está en 0, usamos la cantidad pedida) para que nunca de error
                        $qty = $items->sum(function($i) {
                            return $i->packed_quantity > 0 ? $i->packed_quantity : $i->quantity;
                        });
                        
                        if ($qty <= 0) continue;
                        
                        $price = $items->max('unit_price');
                        $subtotal = $qty * $price;
                        $totalCostoPedido += $subtotal;
                        
                        $article = $items->first()->article;
                        $codigo = $article ? $article->code : 'S/C';
                        $nombre = $article ? $article->name : 'Artículo Eliminado';
                        
                        $tbody .= "
                            <tr class='border-b border-gray-200 dark:border-white/10'>
                                <td class='px-4 py-3 text-sm text-gray-950 dark:text-white'>{$codigo} - {$nombre}</td>
                                <td class='px-4 py-3 text-sm text-center font-medium text-gray-950 dark:text-white'>{$qty}</td>
                                <td class='px-4 py-3 text-sm text-right text-gray-950 dark:text-white'>$ " . number_format($price, 2, ',', '.') . "</td>
                                <td class='px-4 py-3 text-sm font-bold text-right text-gray-950 dark:text-white'>$ " . number_format($subtotal, 2, ',', '.') . "</td>
                            </tr>
                        ";
                    }

                    // HTML Nativo con diseño Dark/Light perfecto
                    $resumenHtml = "
                    <div class='fi-ta-content overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 mb-2'>
                        <div class='overflow-x-auto'>
                            <table class='w-full text-left divide-y divide-gray-200 dark:divide-white/5'>
                                <thead class='bg-gray-50 dark:bg-white/5'>
                                    <tr>
                                        <th class='px-4 py-3 text-xs font-semibold text-gray-950 dark:text-white uppercase tracking-wider'>Artículo</th>
                                        <th class='px-4 py-3 text-xs font-semibold text-center text-gray-950 dark:text-white uppercase tracking-wider'>Cant.</th>
                                        <th class='px-4 py-3 text-xs font-semibold text-right text-gray-950 dark:text-white uppercase tracking-wider'>Precio U.</th>
                                        <th class='px-4 py-3 text-xs font-semibold text-right text-gray-950 dark:text-white uppercase tracking-wider'>Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody class='divide-y divide-gray-200 dark:divide-white/5'>
                                    {$tbody}
                                </tbody>
                                <tfoot class='bg-gray-50 dark:bg-white/5'>
                                    <tr>
                                        <td colspan='3' class='px-4 py-4 text-right text-sm font-bold text-gray-950 dark:text-white uppercase tracking-wider'>
                                            Total Consolidado (Padre e Hijos):
                                        </td>
                                        <td class='px-4 py-4 text-right text-xl font-black text-primary-600 dark:text-primary-400'>
                                            $ " . number_format($totalCostoPedido, 2, ',', '.') . "
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                    ";
                    
                    return [
                        Forms\Components\Placeholder::make('resumen')
                            ->label('')
                            ->content(new HtmlString($resumenHtml)),
                        
                        Forms\Components\Section::make('Configuración de Cobro')->schema([
                            Forms\Components\Grid::make(2)->schema([
                                Forms\Components\Select::make('billing_type')
                                    ->label('Tipo de Facturación')
                                    ->options(['fiscal' => 'Fiscal (100% Blanco)', 'informal' => 'Interno (100% Negro)', 'mixed' => 'Mixto (50/50)'])
                                    ->default($record->client->billing_type ?? 'mixed')
                                    ->required(),
                                    
                                Forms\Components\Select::make('payment_method')
                                    ->label('Método de Pago')
                                    ->options(['cta_cte' => 'Cta Cte', 'efectivo' => 'Efectivo', 'transferencia' => 'Transferencia', 'cheque' => 'Cheque'])
                                    ->default($record->client->last_payment_method ?? 'cta_cte')
                                    ->required()->live(),
                            ]),
                            
                            Forms\Components\Grid::make(2)->schema([
                                Forms\Components\TextInput::make('bank_name')->label('Banco Origen')->placeholder('Ej: Galicia / MP')
                                    ->visible(fn (Get $get) => in_array($get('payment_method'), ['transferencia', 'cheque']))
                                    ->required(fn (Get $get) => in_array($get('payment_method'), ['transferencia', 'cheque'])),
                                Forms\Components\TextInput::make('transaction_id')->label('Nro. Referencia')
                                    ->visible(fn (Get $get) => $get('payment_method') === 'transferencia')
                                    ->required(fn (Get $get) => $get('payment_method') === 'transferencia'),
                                Forms\Components\TextInput::make('check_number')->label('Nro. Cheque')
                                    ->visible(fn (Get $get) => $get('payment_method') === 'cheque')
                                    ->required(fn (Get $get) => $get('payment_method') === 'cheque'),
                                Forms\Components\DatePicker::make('due_date')->label('Vencimiento Cheque')
                                    ->visible(fn (Get $get) => $get('payment_method') === 'cheque')
                                    ->required(fn (Get $get) => $get('payment_method') === 'cheque'),
                            ]),
                        ]),
                    ];
                })
                ->action(function (Order $record, array $data) {
                    $response = \App\Services\AfipService::facturar($record, $data);
                    if ($response['success']) {
                        $record->update(['status' => OrderStatus::Checked, 'invoiced_at' => now()]);
                        $record->children()->update(['status' => OrderStatus::Checked]);
                        Notification::make()->success()->title($response['message'])->send();
                        $this->refreshFormData(['status']);
                    } else {
                        Notification::make()->danger()->title('Error AFIP')->body($response['error'])->send();
                    }
                }),

            // BOTON PAUSAR (STANDBY)
            \Filament\Actions\Action::make('poner_en_standby')
                ->label('Pausar (Standby)')
                ->icon('heroicon-m-pause-circle')
                ->color('warning')
                ->visible(fn (Order $record) => 
                    in_array($record->status instanceof \BackedEnum ? $record->status->value : $record->status, ['assembled', 'checked', 'dispatched']) 
                    && is_null($record->parent_id)
                )
                ->requiresConfirmation()
                ->action(function (Order $record) {
                    $record->update(['status' => OrderStatus::Standby]);
                    $record->children()->update(['status' => OrderStatus::Standby]);
                    Notification::make()->warning()->title('Pedido en Standby')->send();
                    $this->refreshFormData(['status']);
                }),

            // Botón Imprimir PDF (Fixeado URL temporal)
            \Filament\Actions\Action::make('print')
                ->label('Imprimir Picking')
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->url(fn (Order $record) => url('/admin/orders/'.$record->id.'/pdf'))
                ->openUrlInNewTab(),

            // NUEVO BOTÓN CANCELAR / VOLVER
            \Filament\Actions\Action::make('cancelar_edicion')
                ->label('Volver / Cancelar')
                ->color('gray')
                ->icon('heroicon-m-arrow-left')
                ->url(function (Order $record) {
                    $status = $record->status instanceof \BackedEnum ? $record->status->value : $record->status;
                    return OrderResource::getUrl('index', ['activeTab' => $status]);
                }),
                
            // Borrar
            \Filament\Actions\DeleteAction::make()
                ->visible(fn (Order $record) => ($record->status instanceof \BackedEnum ? $record->status->value : $record->status) === 'draft' && is_null($record->parent_id))
                ->before(fn (Order $record) => $record->children()->delete()),
        ];
    }

    protected function getRedirectUrl(): string
    {
        $record = $this->getRecord();
        $status = $record->status instanceof \BackedEnum ? $record->status->value : $record->status;
        
        // Redirige a la tabla, activando la pestaña del estado actual del pedido
        return $this->getResource()::getUrl('index', ['activeTab' => $status]);
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $childGroups = $this->data['child_groups'] ?? [];
        $articleGroups = $this->data['article_groups'] ?? [];
        unset($data['child_groups'], $data['article_groups']);

        return DB::transaction(function () use ($record, $data, $articleGroups, $childGroups) {
            $oldStatus = $record->getOriginal('status');
            $record->update($data);
            $newStatus = $record->status->value;

            // AUTO-ARMADO
            $estadosFinales = ['assembled', 'checked', 'dispatched', 'paid'];
            if ($oldStatus === 'draft' && in_array($newStatus, $estadosFinales)) {
                foreach ($record->items as $item) {
                    if ($item->packed_quantity == 0) {
                        $item->update(['packed_quantity' => $item->quantity]);
                    }
                }
                Notification::make()->warning()->title("Pedido Auto-Armado")->body("Se igualó lo armado a lo pedido.")->send();
            }

            // SINCRONIZACIÓN DE ESTADOS A HIJOS
            $estadosSincro = ['dispatched', 'paid', 'cancelled'];
            if ($oldStatus !== $newStatus && in_array($newStatus, $estadosSincro)) {
                $record->children()->update(['status' => $newStatus]);
            }

            // GUARDAR PADRE
            if ($record->status->value === 'draft') {
                $totalAmount = 0;
                $skusToKeep = []; 

                foreach ($articleGroups as $group) {
                    if (!isset($group['matrix'])) continue;
                    foreach ($group['matrix'] as $row) {
                        foreach ($row as $k => $val) {
                            if (str_starts_with($k, 'qty_') && (int)$val > 0) { 
                                $sizeId = str_replace('qty_', '', $k);
                                $sku = Sku::where('article_id', $group['article_id'])->where('color_id', $row['color_id'])->where('size_id', $sizeId)->first();
                                
                                if ($sku) {
                                    $sub = (int)$val * $sku->article->base_cost;
                                    $record->items()->updateOrCreate(['sku_id' => $sku->id], [
                                        'article_id' => $group['article_id'], 'color_id' => $row['color_id'],
                                        'quantity' => (int)$val, 'unit_price' => $sku->article->base_cost,
                                        'subtotal' => $sub
                                    ]);
                                    $totalAmount += $sub;
                                    $skusToKeep[] = $sku->id; 
                                }
                            }
                        }
                    }
                }
                
                if (!empty($skusToKeep)) {
                    $record->items()->whereNotIn('sku_id', $skusToKeep)->delete();
                } else {
                    $record->items()->delete(); 
                }
                
                $record->update(['total_amount' => $totalAmount]);
            }

            // CREAR HIJO
            if ($record->status->value === 'standby' && count($childGroups) > 0) {
                $child = Order::create([
                    'parent_id' => $record->id, 'client_id' => $record->client_id, 'status' => 'processing', 
                    'order_date' => now(), 'billing_type' => $record->billing_type, 'total_amount' => 0
                ]);
                $totalChild = 0;
                foreach ($childGroups as $group) {
                    foreach ($group['matrix'] as $row) {
                        foreach ($row as $k => $qty) {
                            if (str_starts_with($k, 'qty_') && (int)$qty != 0) {
                                $sizeId = str_replace('qty_', '', $k);
                                $sku = Sku::where('article_id', $group['article_id'])->where('color_id', $row['color_id'])->where('size_id', $sizeId)->first();
                                if ($sku) {
                                    $sub = (int)$qty * $sku->article->base_cost;
                                    $child->items()->create([
                                        'article_id' => $group['article_id'], 'sku_id' => $sku->id, 'color_id' => $row['color_id'],
                                        'quantity' => (int)$qty, 'unit_price' => $sku->article->base_cost, 'subtotal' => $sub
                                    ]);
                                    $totalChild += $sub;
                                }
                            }
                        }
                    }
                }
                $child->update(['total_amount' => $totalChild]);
                $this->data['child_groups'] = [];
                Notification::make()->title("Orden #{$child->id} generada.")->success()->send();
            }
            return $record;
        });
    }
}