<?php

namespace App\Filament\Resources\LocalityResource\Pages;

use App\Filament\Resources\LocalityResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditLocality extends EditRecord
{
    protected static string $resource = LocalityResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
