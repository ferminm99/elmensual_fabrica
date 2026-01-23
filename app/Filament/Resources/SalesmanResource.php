<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SalesmanResource\Pages;
use App\Filament\Resources\SalesmanResource\RelationManagers;
use App\Models\Salesman;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class SalesmanResource extends Resource
{
    protected static ?string $model = Salesman::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?string $navigationGroup = 'Ventas'; 
    protected static ?string $modelLabel = 'Viajante';
    
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                //
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                //
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
            'index' => Pages\ListSalesmen::route('/'),
            'create' => Pages\CreateSalesman::route('/create'),
            'edit' => Pages\EditSalesman::route('/{record}/edit'),
        ];
    }
}