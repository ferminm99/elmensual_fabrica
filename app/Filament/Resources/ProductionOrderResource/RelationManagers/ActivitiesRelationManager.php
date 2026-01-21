<?php

namespace App\Filament\Resources\ProductionOrderResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ActivitiesRelationManager extends RelationManager
{
    protected static string $relationship = 'activities';
    protected static ?string $title = 'Historial de Cambios / Auditoría';
    protected static ?string $icon = 'heroicon-o-eye';

    public function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public function table(Table $table): Table
    {
        return $table
            // 1. FILTRO: Esto oculta los registros basura viejos.
            ->modifyQueryUsing(fn ($query) => $query->whereIn('description', ['created', 'updated', 'deleted', 'restored']))
            
            ->recordTitleAttribute('description')
            ->columns([
                // USUARIO
                Tables\Columns\TextColumn::make('causer.name')
                    ->label('Usuario')
                    ->icon('heroicon-m-user')
                    ->weight('bold'),

                // ACCIÓN
                Tables\Columns\TextColumn::make('description')
                    ->label('Acción')
                    ->badge()
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'created' => 'Creó el registro',
                        'updated' => 'Actualizó datos',
                        'deleted' => 'Borró el registro',
                        'restored' => 'Restauró el registro',
                        default => $state,
                    })
                    ->color(fn ($state) => match ($state) {
                        'created' => 'success',
                        'updated' => 'warning',
                        'deleted' => 'danger',
                        'restored' => 'info',
                        default => 'gray',
                    }),

                // --- AQUÍ ESTÁ EL ARREGLO VISUAL ---
                // Usamos un nombre INVENTADO ('audit_details') para que Filament NO lea el array
                // y así evitamos que dibuje botones dobles.
                Tables\Columns\TextColumn::make('audit_details') 
                    ->label('Detalle de Cambios')
                    ->badge()
                    ->color('info')
                    ->icon('heroicon-m-eye')
                    // Calculamos el estado manualmente:
                    ->getStateUsing(function ($record) {
                        $props = $record->properties;
                        
                        // Si está vacío o es basura, devolvemos null (no muestra nada)
                        if (empty($props)) return null;
                        if (is_array($props) && empty($props)) return null;
                        if (is_string($props) && $props == '[]') return null;

                        // Si hay datos, devolvemos EL TEXTO DEL BOTÓN (una sola vez)
                        return 'Ver Detalles';
                    })
                    // Acción al hacer clic
                    ->action(
                        Tables\Actions\Action::make('ver_cambios')
                            ->label('Auditoría')
                            ->modalHeading('Detalle de Cambios')
                            ->modalSubmitAction(false)
                            ->modalCancelAction(fn ($action) => $action->label('Cerrar'))
                            ->modalContent(function ($record) {
                                return new \Illuminate\Support\HtmlString(
                                    $this->generarHtmlDetalles($record->properties)
                                );
                            })
                    ),

                // FECHA
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i')
                    ->color('gray')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    // ----------------------------------------------------
    // TU LÓGICA DE PARSEO (INTACTA)
    // ----------------------------------------------------
    private function generarHtmlDetalles($state): string
    {
        if (empty($state)) return '<div class="text-gray-500">Sin detalles técnicos.</div>';

        if ($state instanceof \Illuminate\Support\Collection) $state = $state->toArray();
        if (is_object($state)) $state = (array) $state;
        if (is_string($state)) {
            $decoded = json_decode($state, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $repaired = json_decode('[' . $state . ']', true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($repaired)) {
                    $decoded = ['attributes' => $repaired[0] ?? [], 'old' => $repaired[1] ?? []];
                }
            }
            if (is_array($decoded)) $state = $decoded;
        }
        
        if (!is_array($state)) return "<code class='text-xs bg-gray-100 p-2 block'>" . (string)$state . "</code>";

        $attributes = $state['attributes'] ?? $state;
        $old = $state['old'] ?? [];
        
        if (isset($attributes['attributes'])) {
            $old = $attributes['old'] ?? $old;
            $attributes = $attributes['attributes'];
        }

        $html = '';
        $diccionario = [
            'usage_quantity' => 'Cantidad Tela', 'status' => 'Estado', 'raw_material_id' => 'Tela',
            'article_id' => 'Artículo', 'name' => 'Nombre', 'stock_quantity' => 'Stock',
            'cost_per_unit' => 'Costo', 'base_cost' => 'Costo Base', 'average_consumption' => 'Consumo',
            'unit' => 'Unidad', 'price' => 'Precio', 'description' => 'Descripción', 'color_id' => 'Color'
        ];

        $cambiosDetectados = false;

        foreach ($attributes as $key => $newValue) {
            if (in_array($key, ['updated_at', 'created_at', 'deleted_at', 'id', 'user_id'])) continue;
            
            if (is_array($newValue)) $newValue = json_encode($newValue);
            $oldValue = $old[$key] ?? '-';
            if (is_array($oldValue)) $oldValue = json_encode($oldValue);

            if ((string)$newValue !== (string)$oldValue) {
                $cambiosDetectados = true;
                $nombre = $diccionario[$key] ?? ucfirst($key);
                
                $html .= "
                <div class='flex justify-between items-center py-2 border-b border-gray-100 last:border-0'>
                    <span class='font-medium text-gray-700 w-1/3'>{$nombre}</span>
                    <div class='flex-1 flex items-center justify-end space-x-2 text-sm'>
                        <span class='text-red-500 line-through bg-red-50 px-2 py-0.5 rounded'>{$oldValue}</span>
                        <span class='text-gray-400'>&rarr;</span>
                        <span class='text-green-600 font-bold bg-green-50 px-2 py-0.5 rounded'>{$newValue}</span>
                    </div>
                </div>";
            }
        }

        if (!$cambiosDetectados) {
             return '<div class="text-gray-400 italic text-center py-2">Actualización interna (Fechas/Sistema)</div>';
        }

        return "<div class='flex flex-col'>{$html}</div>";
    }
}