<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CategoryResource\Pages;
use App\Models\Category;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
// Ya NO usamos SoftDeletingScope aquí

class CategoryResource extends Resource
{
    protected static ?string $model = Category::class;
    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?string $modelLabel = 'Categoría';
    protected static ?string $pluralModelLabel = 'Categorías';
    protected static ?string $navigationGroup = 'Producción';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Información')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nombre')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre')
                    ->sortable()
                    ->searchable(),
                
                Tables\Columns\TextColumn::make('articles_count')
                    ->label('Artículos Activos')
                    ->counts('articles') // Cuenta solo los activos
                    ->badge(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                
                // LÓGICA DE BORRADO PERSONALIZADA
                Tables\Actions\DeleteAction::make()
                    ->before(function (Tables\Actions\DeleteAction $action, Category $record) {
                        
                        // 1. Si tiene artículos ACTIVOS -> PROHIBIDO BORRAR
                        if ($record->articles()->count() > 0) {
                            Notification::make()
                                ->danger()
                                ->title('No se puede borrar')
                                ->body('Esta categoría tiene artículos activos. Bórralos o muévelos primero.')
                                ->send();
                            
                            $action->cancel();
                            return;
                        }

                        // 2. Si tiene artículos EN PAPELERA (Soft Deleted)
                        // Tenemos que "desvincularlos" para que al borrar la categoría no explote la BD
                        // Buscamos artículos borrados (withTrashed) que sean de esta categoría
                        $trashedArticles = \App\Models\Article::onlyTrashed()
                            ->where('category_id', $record->id)
                            ->get();

                        foreach ($trashedArticles as $article) {
                            $article->category_id = null; // Los dejamos huérfanos
                            $article->save();
                        }
                    }),
            ]);
    }
    
    // Quitamos la función getEloquentQuery() porque ya no usamos soft deletes en categorías.

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCategories::route('/'),
            'create' => Pages\CreateCategory::route('/create'),
            'edit' => Pages\EditCategory::route('/{record}/edit'),
        ];
    }
}