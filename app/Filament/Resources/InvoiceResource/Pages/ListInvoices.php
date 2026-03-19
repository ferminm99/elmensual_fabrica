<?php

namespace App\Filament\Resources\InvoiceResource\Pages;

use App\Filament\Resources\InvoiceResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Actions; // <--- ASEGURATE DE TENER ESTE IMPORT

class ListInvoices extends ListRecords
{
    protected static string $resource = InvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Botón Factura
            Actions\CreateAction::make('create_invoice')
                ->label('Nueva Factura Manual')
                ->icon('heroicon-o-plus')
                ->mutateFormDataUsing(function (array $data): array {
                    $data['tipo_manual'] = 'factura'; // Marcamos que es factura
                    return $data;
                }),

            // Botón Nota de Crédito
            Actions\CreateAction::make('create_nc')
                ->label('Nueva NC Manual')
                ->icon('heroicon-o-minus-circle')
                ->color('danger')
                ->mutateFormDataUsing(function (array $data): array {
                    $data['tipo_manual'] = 'nc'; // Marcamos que es NC
                    return $data;
                }),
        ];
    }
}