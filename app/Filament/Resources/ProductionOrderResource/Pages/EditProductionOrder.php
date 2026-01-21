<?php

namespace App\Filament\Resources\ProductionOrderResource\Pages;

use App\Filament\Resources\ProductionOrderResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditProductionOrder extends EditRecord
{
    protected static string $resource = ProductionOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    // AGREGAR ESTO PARA VER EL HISTORIAL ABAJO
    protected function getFooterWidgets(): array
    {
        return [
            // Esto muestra una tabla con los cambios
            // Nota: Si este widget específico no te sale, avísame y hacemos una tabla manual simple
        ];
    }
}