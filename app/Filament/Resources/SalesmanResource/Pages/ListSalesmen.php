<?php

namespace App\Filament\Resources\SalesmanResource\Pages;

use App\Filament\Resources\SalesmanResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSalesmen extends ListRecords
{
    protected static string $resource = SalesmanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
