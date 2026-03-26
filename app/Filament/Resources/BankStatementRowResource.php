<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BankStatementRowResource\Pages;
use App\Models\BankStatementRow;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;
use App\Models\Transaction;

class BankStatementRowResource extends Resource
{
    protected static ?string $model = BankStatementRow::class;
    protected static ?string $navigationIcon = 'heroicon-o-check-badge';
    protected static ?string $navigationGroup = 'Tesorería';
    protected static ?string $modelLabel = 'Movimiento Bancario';
    protected static ?string $pluralModelLabel = 'Conciliación (Aprobar Pagos)';

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('date', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('date')
                    ->label('Fecha')
                    ->date('d/m/Y'),
                
                Tables\Columns\TextColumn::make('statement.account.name')
                    ->label('Banco')
                    ->badge(),

                Tables\Columns\TextColumn::make('name_origin')
                    ->label('Origen / Concepto')
                    ->description(fn($record) => $record->concept . ' - CUIT: ' . $record->cuit_origin)
                    ->searchable(),

                Tables\Columns\TextColumn::make('amount')
                    ->label('Monto')
                    ->money('ARS')
                    ->weight('black')
                    ->color(fn($state) => $state >= 0 ? 'success' : 'danger'),

                // ACÁ ESTÁ LA MAGIA: Si el sistema detectó al cliente, aparece acá.
                Tables\Columns\TextColumn::make('client.name')
                    ->label('Cliente Detectado')
                    ->color(fn($state) => $state ? 'success' : 'danger')
                    ->weight('bold')
                    ->default('NO DETECTADO ❌'),

                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->colors(['warning' => 'pending', 'success' => 'approved', 'gray' => 'ignored']),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(['pending' => 'Pendientes', 'approved' => 'Aprobados'])
                    ->default('pending'),
            ])
            ->actions([
                // Botón para asignar cliente a mano si el banco no mandó el CUIT
                Tables\Actions\Action::make('asignar_cliente')
                    ->label('Asignar Cliente')
                    ->icon('heroicon-o-user-plus')
                    ->color('warning')
                    ->visible(fn($record) => $record->status === 'pending' && !$record->client_id && $record->amount > 0)
                    ->form([
                        Forms\Components\Select::make('client_id')
                            ->label('Buscar Cliente')
                            ->relationship('client', 'name')
                            ->searchable()
                            ->required(),
                    ])
                    ->action(fn($record, $data) => $record->update(['client_id' => $data['client_id']])),
                    
                // Botón de Impacto Real
                Tables\Actions\Action::make('aprobar')
                    ->label('Aprobar Cobro')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn($record) => $record->status === 'pending' && $record->client_id && $record->amount > 0)
                    ->form([
                        Forms\Components\Select::make('imputation_type')
                            ->label('¿A qué deuda descontar este pago?')
                            ->options([
                                'fiscal' => 'Deuda Blanca (Fiscal)',
                                'internal' => 'Deuda Negra (Interna)',
                            ])->default('fiscal')->required()
                    ])
                    ->action(function($record, array $data) {
                        DB::transaction(function() use ($record, $data) {
                            // 1. Descontamos deuda al cliente
                            if ($data['imputation_type'] === 'fiscal') {
                                $record->client->decrement('fiscal_debt', $record->amount);
                            } else {
                                $record->client->decrement('internal_debt', $record->amount);
                            }
                            
                            // 2. Sumamos la plata a nuestra caja/banco real
                            $record->statement->account->increment('current_balance', $record->amount);
                            
                            // 3. Anotamos la transacción
                            Transaction::create([
                                'company_account_id' => $record->statement->company_account_id,
                                'client_id' => $record->client_id,
                                'type' => 'Income', // Ingreso
                                'amount' => $record->amount,
                                'description' => "Cobro Transferencia: " . $record->name_origin,
                                'concept' => 'Cobro a Cliente',
                                'origin' => $data['imputation_type'] === 'fiscal' ? 'Fiscal' : 'Internal',
                            ]);
                            
                            // 4. Marcamos la fila como aprobada para que desaparezca de pendientes
                            $record->update(['status' => 'approved']);
                        });
                        \Filament\Notifications\Notification::make()->success()->title('Cobro impactado con éxito')->send();
                    }),

                // Botón para gastos del banco (mantenimiento de cuenta, etc)
                Tables\Actions\Action::make('ignorar')
                    ->label('Ignorar / Gasto')
                    ->icon('heroicon-o-archive-box-x-mark')
                    ->color('gray')
                    ->visible(fn($record) => $record->status === 'pending')
                    ->action(fn($record) => $record->update(['status' => 'ignored'])),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBankStatementRows::route('/'),
        ];
    }
}