<?php

namespace App\Filament\Resources\SalaryPeriodResource\Pages;

use App\Filament\Resources\SalaryPeriodResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSalaryPeriod extends EditRecord
{
    protected static string $resource = SalaryPeriodResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
