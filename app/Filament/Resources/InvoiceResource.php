<?php

namespace App\Filament\Resources;

use App\Filament\Resources\InvoiceResource\Pages;
use App\Models\Invoice;
use App\Services\AfipService;
use Filament\Forms;
use Filament\Forms\Get;
use Filament\Forms\Set;
use App\Models\Article;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Notifications\Notification;
use Illuminate\Support\HtmlString;
use Illuminate\Database\Eloquent\Builder; 

class InvoiceResource extends Resource
{
    protected static ?string $model = Invoice::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-chart-bar';
     protected static ?string $navigationGroup = 'Contabilidad';
    protected static ?string $modelLabel = 'Comprobante Fiscal';
    protected static ?string $pluralModelLabel = 'Libro de IVA (Facturas)';

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label('Fecha Emisión')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                TextColumn::make('invoice_type')
                    ->label('Tipo')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'B', 'A' => 'success',
                        'NC' => 'danger',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'B' => 'Factura B',
                        'A' => 'Factura A',
                        'NC' => 'Nota de Crédito',
                    }),

                TextColumn::make('number')
                    ->label('Número')
                    ->searchable()
                    ->weight('bold')
                    ->copyable()
                    ->copyMessage('Número copiado'),

                TextColumn::make('order.client.name')
                    ->label('Cliente')
                    ->searchable()
                    ->description(fn (Invoice $record) => "Pedido #" . $record->order_id),

                TextColumn::make('total_fiscal')
                    ->label('Monto Total')
                    ->money('ARS')
                    ->alignment('right')
                    ->weight('black')
                    // El monto de las NC sale en rojo
                    ->color(fn (Invoice $record) => $record->invoice_type === 'NC' ? 'danger' : 'gray'),

                TextColumn::make('cae_afip')
                    ->label('CAE (AFIP)')
                    ->fontFamily('mono')
                    ->size('xs')
                    ->toggleable(isToggledHiddenByDefault: false),
            ])
            ->filters([
                SelectFilter::make('invoice_type')
                    ->label('Tipo de Documento')
                    ->options([
                        'B' => 'Facturas',
                        'NC' => 'Notas de Crédito',
                    ]),
                Tables\Filters\Filter::make('created_at')
                    ->form([
                        Forms\Components\DatePicker::make('desde'),
                        Forms\Components\DatePicker::make('hasta'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['desde'], fn ($q) => $q->whereDate('created_at', '>=', $data['desde']))
                            ->when($data['hasta'], fn ($q) => $q->whereDate('created_at', '<=', $data['hasta']));
                    })
            ])
            ->actions([
                // ACCIÓN 1: Ver PDF en pestaña aparte
                Tables\Actions\Action::make('view_pdf')
                    ->label('Ver PDF')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->url(fn (Invoice $record) => route('order.invoice.download', [
                        'order' => $record->order_id, 
                        'invoice_id' => $record->id
                    ]))
                    ->openUrlInNewTab(),

                // ACCIÓN 2: Anular (Solo Facturas que no tengan NC asociada)
                Tables\Actions\Action::make('anular_manual')
                    ->label('Anular (NC)')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('¿Anular este comprobante?')
                    ->modalDescription('Se generará una Nota de Crédito en AFIP por el total de esta factura. Esta acción no se puede deshacer.')
                    ->visible(function (Invoice $record) {
                        // Solo se anulan Facturas B
                        if ($record->invoice_type !== 'B') return false;
                        // Solo si no tiene ya un hijo (una NC que la apunte como parent_id)
                        return !Invoice::where('parent_id', $record->id)->exists();
                    })
                    ->action(function (Invoice $record) {
                        // Llamamos al Service con el pedido asociado
                        $response = AfipService::anular($record->order);
                        
                        if ($response['success']) {
                            Notification::make()
                                ->success()
                                ->title('Nota de Crédito Generada')
                                ->body('El comprobante se anuló correctamente en AFIP.')
                                ->send();
                        } else {
                            Notification::make()
                                ->danger()
                                ->title('Error en AFIP')
                                ->body($response['error'])
                                ->persistent()
                                ->send();
                        }
                    }),
            ])
            ->bulkActions([]);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Tipo y Receptor')->schema([
                Forms\Components\Grid::make(3)->schema([
                    // Selector de Tipo: Factura o NC
                    Forms\Components\Select::make('invoice_type')
                        ->label('Tipo de Comprobante')
                        ->options([
                            'B' => 'Factura B',
                            'NC' => 'Nota de Crédito B',
                        ])
                        ->default('B')
                        ->required()
                        ->native(false),

                    Forms\Components\Select::make('client_id')
                        ->label('Cliente Registrado')
                        ->relationship('client', 'name')
                        ->searchable()
                        ->live()
                        ->placeholder('Cliente eventual...'),

                    Forms\Components\Group::make()->schema([
                        Forms\Components\TextInput::make('manual_client_name')
                            ->label('Nombre / Razón Social')
                            ->required(fn (Get $get) => !$get('client_id')),
                        Forms\Components\TextInput::make('manual_client_tax_id')
                            ->label('CUIT / DNI')
                            ->placeholder('0 para Consumidor Final')
                            ->required(fn (Get $get) => !$get('client_id')),
                    ])->visible(fn (Get $get) => !$get('client_id'))->columnSpan(1),
                ]),
            ]),

            Forms\Components\Section::make('Artículos')->schema([
                Forms\Components\Repeater::make('items')
                ->schema([
                    Forms\Components\Select::make('article_id')
                        ->label('Artículo')
                        ->options(Article::pluck('name', 'id'))
                        ->required()
                        ->live() // Esto hace que el cambio sea instantáneo
                        ->afterStateUpdated(function ($state, Set $set) {
                            if ($state) {
                                $article = Article::find($state);
                                // USAMOS base_cost que es tu variable real
                                $set('unit_price', $article?->base_cost ?? 0);
                            }
                        })
                        ->columnSpan(2),

                    Forms\Components\TextInput::make('quantity')
                        ->label('Cant.')
                        ->numeric()
                        ->default(1)
                        ->required(),

                    Forms\Components\TextInput::make('unit_price')
                        ->label('Precio Unit.')
                        ->numeric()
                        ->prefix('$')
                        ->required()
                        ->helperText('Se cargó el costo base, podés editarlo si querés.'),
                ])
                ->columns(4)
                    ->minItems(1),
            ]),
        ]);
    }
    
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInvoices::route('/'),
            'create' => Pages\CreateInvoice::route('/create'), // HABILITAR ESTO
        ];
    }
}