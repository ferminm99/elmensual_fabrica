<?php

namespace App\Filament\Widgets;

use App\Models\CompanyAccount;
use App\Models\Check;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use App\Enums\CheckStatus;

class TreasuryStatsOverview extends BaseWidget
{
    protected function getStats(): array
    {
        // Calculate Totals
        $totalBank = CompanyAccount::where('type', 'Bank')->sum('current_balance');
        $totalCash = CompanyAccount::where('type', 'Cash')->sum('current_balance');
        $checksPortfolio = Check::where('status', CheckStatus::IN_PORTFOLIO)->sum('amount');

        return [
            Stat::make('Bank Balance', '$' . number_format($totalBank, 2))
                ->description('Total in Banks')
                ->descriptionIcon('heroicon-m-building-library')
                ->color('info'),

            Stat::make('Cash On Hand', '$' . number_format($totalCash, 2))
                ->description('Safe & Wallets')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success'),

            Stat::make('Checks in Portfolio', '$' . number_format($checksPortfolio, 2))
                ->description('Third Party Checks')
                ->descriptionIcon('heroicon-m-document-text')
                ->color('warning'),
        ];
    }
}