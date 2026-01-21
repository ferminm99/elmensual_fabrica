<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ArticleResource\Pages;
// Importamos los Managers de Relación
use App\Filament\Resources\ArticleResource\RelationManagers\RecipesRelationManager;
use App\Filament\Resources\ArticleResource\RelationManagers\SkusRelationManager; 
use App\Filament\Resources\ProductionOrderResource\RelationManagers\ActivitiesRelationManager;
use App\Models\Article;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection; 
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ArticleResource extends Resource
{
    protected static ?string $model = Article::class;
    protected static ?string $navigationIcon = 'heroicon-o-tag';
    protected static ?string $modelLabel = 'Artículo';
    protected static ?string $pluralModelLabel = 'Artículos';
    protected static ?string $navigationGroup = 'Producción';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                // --- SECCIÓN ÚNICA: DATOS GENERALES ---
                // (Ya no ponemos el Repeater aquí para que no se haga pesado)
                Forms\Components\Section::make('Detalle del Producto')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nombre del Artículo')
                            ->required()
                            ->maxLength(255),
                        
                        Forms\Components\TextInput::make('code')
                            ->label('Código Padre')
                            ->helperText('Código genérico de la familia')
                            ->unique(ignoreRecord: true)
                            ->required(),
                        
                        Forms\Components\Select::make('category_id')
                            ->label('Categoría')
                            ->relationship('category', 'name')
                            ->searchable()
                            ->preload()
                            ->createOptionForm([
                                Forms\Components\TextInput::make('name')->required()->label('Nueva Categoría'),
                            ])
                            ->required(),

                        Forms\Components\TextInput::make('average_consumption')
                            ->label('Consumo Promedio de Tela')
                            ->helperText('¿Cuántos metros/kilos consume 1 unidad? (Ej: 1.20)')
                            ->numeric()
                            ->step(0.01),
                        
                        Forms\Components\TextInput::make('base_cost')
                            ->label('Costo Base')
                            ->numeric()
                            ->prefix('$')
                            ->required(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label('Código')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                
                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable()
                    ->wrap(),
                
                Tables\Columns\TextColumn::make('category.name')
                    ->label('Categoría')
                    ->sortable()
                    ->wrap(),
                    
                Tables\Columns\TextColumn::make('base_cost')
                    ->label('Costo Base')
                    ->money('ARS')
                    ->sortable(),

                // Columna calculada: Suma el stock de todos los hijos
                Tables\Columns\TextColumn::make('skus_sum_stock_quantity')
                    ->sum('skus', 'stock_quantity')
                    ->label('Stock Total')
                    ->sortable()
                    ->badge()
                    ->color(fn ($state) => $state > 0 ? 'success' : 'danger'),
            ])
            ->headerActions([
                Tables\Actions\Action::make('print_list')
                    ->label('Imprimir Lista')
                    ->icon('heroicon-o-printer')
                    ->url(route('price-list.pdf'))
                    ->openUrlInNewTab(),
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
                Tables\Actions\ForceDeleteAction::make(), 
                Tables\Actions\RestoreAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                    
                    Tables\Actions\BulkAction::make('update_prices')
                        ->label('Actualizar Precios')
                        ->icon('heroicon-o-currency-dollar')
                        ->color('success')
                        ->form([
                            Forms\Components\TextInput::make('percentage')
                                ->label('Porcentaje %')->numeric()->required(),
                            Forms\Components\Select::make('rounding')
                                ->label('Redondeo')
                                ->options([
                                    'none' => 'Exacto',
                                    'integer' => 'Al Peso',
                                    'ten' => 'A la Decena',
                                    'hundred' => 'A la Centena',
                                ])->default('ten')->required(),
                        ])
                        ->action(function (Collection $records, array $data) {
                            $percent = 1 + ($data['percentage'] / 100);
                            foreach ($records as $record) {
                                $newPrice = $record->base_cost * $percent;
                                if ($data['rounding'] === 'integer') $newPrice = ceil($newPrice);
                                elseif ($data['rounding'] === 'ten') $newPrice = ceil($newPrice / 10) * 10; 
                                elseif ($data['rounding'] === 'hundred') $newPrice = ceil($newPrice / 100) * 100; 
                                $record->update(['base_cost' => $newPrice]);
                            }
                            Notification::make()->title('Precios actualizados')->success()->send();
                        }),
                ]),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([ SoftDeletingScope::class ]);
    }

    public static function getRelations(): array
    {
        return [
            // AQUÍ AGREGAMOS LA GESTIÓN DE SKUS (La tabla de abajo)
            SkusRelationManager::class, 
            RecipesRelationManager::class,
            ActivitiesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListArticles::route('/'),
            'create' => Pages\CreateArticle::route('/create'),
            'edit' => Pages\EditArticle::route('/{record}/edit'),
        ];
    }
}