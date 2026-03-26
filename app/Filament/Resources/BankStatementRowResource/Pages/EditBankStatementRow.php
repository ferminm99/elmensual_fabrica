<?php

namespace App\Filament\Resources\BankStatementRowResource\Pages;

use App\Filament\Resources\BankStatementRowResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditBankStatementRow extends EditRecord
{
    protected static string $resource = BankStatementRowResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
