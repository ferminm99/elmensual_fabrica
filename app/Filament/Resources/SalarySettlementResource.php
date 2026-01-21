<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SalarySettlementResource\Pages;
use App\Filament\Resources\SalarySettlementResource\RelationManagers;
use App\Models\SalarySettlement;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class SalarySettlementResource extends Resource
{
    protected static ?string $model = SalarySettlement::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?string $navigationGroup = 'Tesorería';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('period_id')
                    ->relationship('period', 'id')
                    ->required(),
                Forms\Components\Select::make('employee_id')
                    ->relationship('employee', 'name')
                    ->required(),
                Forms\Components\TextInput::make('gross_amount')
                    ->required()
                    ->numeric(),
                Forms\Components\TextInput::make('net_amount')
                    ->required()
                    ->numeric(),
                Forms\Components\TextInput::make('pdf_path')
                    ->maxLength(255),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('period.id')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('employee.name')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('gross_amount')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('net_amount')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('pdf_path')
                    ->searchable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSalarySettlements::route('/'),
            'create' => Pages\CreateSalarySettlement::route('/create'),
            'edit' => Pages\EditSalarySettlement::route('/{record}/edit'),
        ];
    }
}