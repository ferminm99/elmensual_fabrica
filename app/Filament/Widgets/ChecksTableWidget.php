<?php

namespace App\Filament\Widgets;

use App\Models\Check;
use App\Models\CompanyAccount;
use App\Models\Transaction;
use App\Enums\CheckStatus;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Filament\Forms;
use Illuminate\Database\Eloquent\Model;

class ChecksTableWidget extends BaseWidget
{
    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(Check::query()->where('status', CheckStatus::IN_PORTFOLIO)) // Only show checks on hand
            ->columns([
                Tables\Columns\TextColumn::make('number')->searchable(),
                Tables\Columns\TextColumn::make('bank_name'),
                Tables\Columns\TextColumn::make('amount')->money('ARS'),
                Tables\Columns\TextColumn::make('payment_date')->date(),
            ])
            ->actions([
                Tables\Actions\Action::make('deposit')
                    ->label('Deposit')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->form([
                        Forms\Components\Select::make('company_account_id')
                            ->label('Destination Bank Account')
                            ->options(CompanyAccount::where('type', 'Bank')->pluck('name', 'id'))
                            ->required(),
                    ])
                    ->action(function (Model $record, array $data) {
                        // 1. Update Check Status
                        $record->update(['status' => CheckStatus::DEPOSITED]);

                        // 2. Create Transaction
                        Transaction::create([
                            'company_account_id' => $data['company_account_id'],
                            'origin' => 'Fiscal', // Default or derived logic
                            'type' => 'Income',
                            'concept' => "Check Deposit #{$record->number}",
                            'amount' => $record->amount,
                        ]);
                    })
                    ->requiresConfirmation(),
            ]);
    }
}