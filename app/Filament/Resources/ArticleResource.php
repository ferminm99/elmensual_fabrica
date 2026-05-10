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

                // Mostramos el precio anterior oculto por defecto para auditoría
                Tables\Columns\TextColumn::make('previous_cost')
                    ->label('Costo Anterior')
                    ->money('ARS')
                    ->color('gray')
                    ->toggleable(isToggledHiddenByDefault: true),

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
                    
                    // ACCIÓN 1: APLICAR AUMENTO (Guarda el precio viejo)
                    Tables\Actions\BulkAction::make('update_prices')
                        ->label('Actualizar Precios')
                        ->icon('heroicon-o-arrow-trending-up')
                        ->color('success')
                        ->form([
                            Forms\Components\TextInput::make('percentage')
                                ->label('Porcentaje de Aumento %')
                                ->numeric()
                                ->required(),
                            Forms\Components\Select::make('rounding')
                                ->label('Redondeo')
                                ->options([
                                    'none' => 'Exacto (Con centavos)',
                                    'integer' => 'Al Peso Exacto',
                                    'ten' => 'A la Decena (Termina en 0)',
                                    'hundred' => 'A la Centena (Termina en 00)',
                                ])->default('ten')->required(),
                        ])
                        ->action(function (Collection $records, array $data) {
                            $percent = 1 + ($data['percentage'] / 100);
                            $conteo = 0;
                            
                            foreach ($records as $record) {
                                // 1. Guardamos el precio actual en "previous_cost" como un backup
                                $precioViejo = $record->base_cost;
                                
                                // 2. Calculamos el precio nuevo
                                $newPrice = $precioViejo * $percent;
                                if ($data['rounding'] === 'integer') $newPrice = ceil($newPrice);
                                elseif ($data['rounding'] === 'ten') $newPrice = ceil($newPrice / 10) * 10; 
                                elseif ($data['rounding'] === 'hundred') $newPrice = ceil($newPrice / 100) * 100; 
                                
                                // 3. Actualizamos ambos campos
                                $record->update([
                                    'previous_cost' => $precioViejo, 
                                    'base_cost' => $newPrice
                                ]);
                                $conteo++;
                            }
                            
                            Notification::make()->title("Se aumentaron los precios de {$conteo} artículos.")->success()->send();
                        }),

                    // ACCIÓN 2: DESHACER ÚLTIMO AUMENTO
                    Tables\Actions\BulkAction::make('undo_price_update')
                        ->label('Deshacer Último Aumento')
                        ->icon('heroicon-o-arrow-uturn-left')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->modalHeading('¿Estás seguro de deshacer el último aumento?')
                        ->modalDescription('Esta acción volverá a establecer el "Costo Base" a su valor anterior exacto para los artículos seleccionados. Solo funciona 1 vez por artículo.')
                        ->action(function (Collection $records) {
                            $conteo = 0;
                            
                            foreach ($records as $record) {
                                // Solo lo hace si el artículo tiene un precio anterior guardado
                                if (!is_null($record->previous_cost)) {
                                    $record->update([
                                        'base_cost' => $record->previous_cost,
                                        // Vaciamos el previous_cost para no dejar que deshagan al infinito y hagan lío
                                        'previous_cost' => null 
                                    ]);
                                    $conteo++;
                                }
                            }

                            if ($conteo > 0) {
                                Notification::make()->title("Se restauró el precio original de {$conteo} artículos.")->success()->send();
                            } else {
                                Notification::make()->title('Ningún artículo tenía un precio anterior guardado.')->warning()->send();
                            }
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