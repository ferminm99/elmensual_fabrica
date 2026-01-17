<?php

namespace App\Filament\Resources\CompanyAccountResource\Pages;

use App\Filament\Resources\CompanyAccountResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCompanyAccounts extends ListRecords
{
    protected static string $resource = CompanyAccountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
