<?php

namespace App\Filament\Resources\SalarySettlementResource\Pages;

use App\Filament\Resources\SalarySettlementResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSalarySettlements extends ListRecords
{
    protected static string $resource = SalarySettlementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
