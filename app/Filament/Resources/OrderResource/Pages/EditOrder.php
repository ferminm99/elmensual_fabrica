<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Filament\Actions;
use Barryvdh\DomPDF\Facade\Pdf; // Para tu botón de imprimir

class EditOrder extends EditRecord
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
            // Tu botón de imprimir que agregamos antes
            Actions\Action::make('print')
                ->label('Imprimir Armado')
                ->icon('heroicon-o-printer')
                ->color('success')
                ->action(function () {
                    $record = $this->getRecord();
                    return response()->streamDownload(function () use ($record) {
                        echo Pdf::loadView('pdf.picking-list', ['order' => $record])->output();
                    }, 'pedido-' . $record->id . '.pdf');
                }),
        ];
    }

    // A. RECUPERAR DATOS (De la BD -> Al Formulario Visual)
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $order = $this->getRecord();
        
        // Agrupamos los items planos por Artículo
        $grouped = $order->items->groupBy('article_id');
        $articleGroupsForm = [];

        foreach ($grouped as $articleId => $rows) {
            $variants = [];
            foreach ($rows as $row) {
                $variants[] = [
                    'color_id'   => $row->color_id,
                    'sku_id'     => $row->sku_id,
                    'quantity'   => $row->quantity,
                    'unit_price' => $row->unit_price,
                ];
            }
            $articleGroupsForm[] = [
                'article_id' => $articleId,
                'variants'   => $variants,
            ];
        }

        // Le pasamos los grupos armados al formulario
        $data['article_groups'] = $articleGroupsForm;
        return $data;
    }

    // B. GUARDAR CAMBIOS (Del Formulario -> A la BD)
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $articleGroups = $data['article_groups'] ?? [];
        unset($data['article_groups']); // Sacamos lo virtual

        return DB::transaction(function () use ($record, $data, $articleGroups) {
            // Actualizamos datos básicos (Cliente, fecha, etc)
            $record->update($data);

            // ESTRATEGIA SEGURA: Borramos items viejos y recreamos
            $record->items()->delete();

            $totalAmount = 0;

            foreach ($articleGroups as $group) {
                $articleId = $group['article_id'];
                $variants = $group['variants'] ?? [];

                foreach ($variants as $variant) {
                    $qty = intval($variant['quantity']);
                    $price = floatval($variant['unit_price']);
                    $subtotal = $qty * $price;

                    $record->items()->create([
                        'article_id' => $articleId,
                        'sku_id'     => $variant['sku_id'],
                        'color_id'   => $variant['color_id'],
                        'quantity'   => $qty,
                        'unit_price' => $price,
                        'subtotal'   => $subtotal,
                    ]);

                    $totalAmount += $subtotal;
                }
            }

            $record->update(['total_amount' => $totalAmount]);

            return $record;
        });
    }
    
}