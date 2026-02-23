<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Enums\OrderStatus;
use App\Filament\Resources\OrderResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use Illuminate\Contracts\Support\Htmlable;
use Filament\Support\Colors\Color; // <-- IMPORTANTE: Importamos la paleta de colores de Filament

class ListOrders extends ListRecords
{
    protected static string $resource = OrderResource::class;

    public function getSubheading(): string | Htmlable | null
    {
        return new HtmlString('
            <style>
                nav.fi-tabs {
                    flex-wrap: wrap !important;
                    overflow: visible !important;
                    row-gap: 8px !important;
                    padding-bottom: 4px !important;
                }
                nav.fi-tabs > button, 
                nav.fi-tabs > a {
                    white-space: nowrap !important;
                    flex-shrink: 0 !important;
                }
            </style>
        ');
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    public function getTabs(): array
    {
        return [
            'todos' => Tab::make('Todos'),
            
            'borradores' => Tab::make('Borradores')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', OrderStatus::Draft))
                ->badge(OrderResource::getModel()::where('status', OrderStatus::Draft)->count())
                ->badgeColor('gray'),

            'para_armar' => Tab::make('Para Armar')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', OrderStatus::Processing))
                ->badge(OrderResource::getModel()::where('status', OrderStatus::Processing)->count())
                ->badgeColor('warning'),

            'armados' => Tab::make('Armados')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', OrderStatus::Assembled))
                ->badge(OrderResource::getModel()::where('status', OrderStatus::Assembled)->count())
                ->badgeColor('info'),

            'standby' => Tab::make('Standby')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', OrderStatus::Standby))
                ->badge(OrderResource::getModel()::where('status', OrderStatus::Standby)->count())
                ->badgeColor(Color::Fuchsia), // <-- COLOR ESPECIAL: Fucsia (Magenta brillante)
            
            'verificados' => Tab::make('Facturados')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', OrderStatus::Checked))
                ->badge(OrderResource::getModel()::where('status', OrderStatus::Checked)->count())
                ->badgeColor(Color::Indigo), // <-- COLOR ESPECIAL: Índigo (Azul violáceo oscuro)

            'en_viajante' => Tab::make('Para Viajante')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', OrderStatus::Dispatched))
                ->badge(OrderResource::getModel()::where('status', OrderStatus::Dispatched)->count())
                ->badgeColor('success'),

            'pagados' => Tab::make('Finalizados')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', OrderStatus::Paid)),
                
            'cancelados' => Tab::make('Cancelados')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', OrderStatus::Cancelled))
                ->badgeColor('danger'),
        ];
    }
}