<?php

namespace App\Filament\Pages;

use App\Models\Order;
use App\Models\Zone;
use App\Models\Locality;
use App\Enums\OrderStatus;
use Filament\Pages\Page;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Section;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

class LogisticsManager extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-map';
    protected static ?string $navigationGroup = 'Logística';
    protected static ?string $title = 'Hoja de Ruta';
    protected static string $view = 'filament.pages.logistics-manager';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Configuración de Viaje')
                    ->description('Seleccioná la zona para ver qué pedidos están listos.')
                    ->schema([
                        Select::make('zone_id')
                            ->label('Zona de Viaje')
                            ->options(Zone::all()->pluck('name', 'id'))
                            ->live() // Importante: para que refresque las localidades al cambiar
                            ->afterStateUpdated(fn ($state, callable $set) => $set('localities', []))
                            ->required(),

                        CheckboxList::make('localities')
                            ->label('Localidades a Visitar')
                            ->helperText('Desmarcá las localidades donde NO se pasará hoy. Solo mostramos las que tienen pedidos pendientes.')
                            ->options(function (callable $get) {
                                $zoneId = $get('zone_id');
                                if (!$zoneId) return [];

                                // Traemos localidades de esa zona que tengan pedidos en borrador/confirmados
                                return Locality::where('zone_id', $zoneId)
                                    ->whereHas('clients.orders', function ($query) {
                                        // Aca asumimos que los pedidos nuevos entran como 'Draft'. 
                                        // Si tenés un estado intermedio 'Confirmed', cambialo acá.
                                        $query->where('status', OrderStatus::Draft); 
                                    })
                                    ->pluck('name', 'id');
                            })
                            ->bulkToggleable()
                            ->columns(3)
                            ->gridDirection('row')
                            ->live(), // Reactivo para saber qué seleccionaste
                    ])
            ])
            ->statePath('data');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('generateRoute')
                ->label('Enviar a Armado')
                ->icon('heroicon-o-truck')
                ->color('primary')
                ->requiresConfirmation()
                ->modalHeading('¿Confirmar Hoja de Ruta?')
                ->modalDescription(function () {
                    $data = $this->form->getState();
                    if (empty($data['zone_id'])) return 'Seleccioná una zona primero.';
                    
                    // Contamos cuántos pedidos van a salir
                    $count = Order::whereHas('client', function ($q) use ($data) {
                        $q->whereIn('locality_id', $data['localities'] ?? []);
                    })->where('status', OrderStatus::Draft)->count();

                    return "Se enviarán {$count} pedidos al depósito para su armado inmediato.";
                })
                ->action(function () {
                    $data = $this->form->getState();
                    
                    // Actualización Masiva
                    Order::whereHas('client', function ($q) use ($data) {
                        $q->whereIn('locality_id', $data['localities'] ?? []);
                    })
                    ->where('status', OrderStatus::Draft)
                    ->update(['status' => OrderStatus::Processing]); // Pasan a "Para Armar"

                    Notification::make()
                        ->title('Hoja de Ruta Generada')
                        ->body('Los pedidos ya aparecen en las tablets del depósito.')
                        ->success()
                        ->send();
                    
                    $this->form->fill(); // Limpiar
                })
        ];
    }
}