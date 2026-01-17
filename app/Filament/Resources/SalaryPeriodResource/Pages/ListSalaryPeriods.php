<?php

namespace App\Filament\Resources\SalaryPeriodResource\Pages;

use App\Filament\Resources\SalaryPeriodResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSalaryPeriods extends ListRecords
{
    protected static string $resource = SalaryPeriodResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
