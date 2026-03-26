<?php

namespace App\Filament\Resources\BankStatementRowResource\Pages;

use App\Filament\Resources\BankStatementRowResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListBankStatementRows extends ListRecords
{
    protected static string $resource = BankStatementRowResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
