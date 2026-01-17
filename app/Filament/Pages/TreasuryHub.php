<?php

namespace App\Filament\Pages;
use Illuminate\Support\Facades\Auth;
use Filament\Pages\Page;
use App\Filament\Widgets\TreasuryStatsOverview;
use App\Filament\Widgets\ChecksTableWidget;

class TreasuryHub extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-banknotes';
    protected static ?string $navigationLabel = 'Treasury Hub';
    protected static string $view = 'filament.pages.treasury-hub';

    public static function canAccess(): bool
    {
        // Solo puede entrar si tiene el rol "Super Admin" o "Gerencia"
        return Auth::user()->hasAnyRole(['Admin', 'Gerencia']);
    }
    // Register the widgets used on this page
    protected function getHeaderWidgets(): array
    {
        return [
            TreasuryStatsOverview::class,
            ChecksTableWidget::class,
        ];
    }
}