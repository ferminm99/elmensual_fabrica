<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CompanyAccountResource\Pages;
use App\Models\CompanyAccount;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Illuminate\Support\Facades\DB;
use App\Models\BankStatement;
use App\Models\BankStatementRow;
use App\Models\Client;

class CompanyAccountResource extends Resource
{
    protected static ?string $model = CompanyAccount::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-library';
    protected static ?string $modelLabel = 'Cuenta / Caja';
    protected static ?string $pluralModelLabel = 'Cajas y Bancos';
    protected static ?string $navigationGroup = 'Tesorería'; // Agrupamos aquí

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Card::make()->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('Nombre de la Cuenta')
                        ->placeholder('Ej: Banco Galicia CC, Caja Chica')
                        ->required(),
                    
                    Forms\Components\Select::make('type')
                        ->label('Tipo')
                        ->options([
                            'bank' => 'Banco',
                            'cash' => 'Efectivo / Caja Física',
                            'wallet' => 'Billetera Virtual (MP, etc)',
                        ])
                        ->required(),

                    Forms\Components\TextInput::make('cbu')
                        ->label('CBU / CVU')
                        ->visible(fn (Forms\Get $get) => $get('type') !== 'cash'),

                    Forms\Components\TextInput::make('currency')
                        ->label('Moneda')
                        ->default('ARS')
                        ->disabled(), // Por ahora solo pesos para no complicar

                    Forms\Components\TextInput::make('current_balance')
                        ->label('Saldo Actual')
                        ->prefix('$')
                        ->numeric()
                        ->default(0)
                        ->helperText('Este saldo se actualiza solo con los cobros y pagos.'),
                ])->columns(2)
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Cuenta')
                    ->weight('bold')
                    ->icon(fn ($record) => match ($record->type) {
                        'bank' => 'heroicon-o-building-library',
                        'cash' => 'heroicon-o-banknotes',
                        'wallet' => 'heroicon-o-device-phone-mobile',
                        default => 'heroicon-o-credit-card',
                    }),

                Tables\Columns\TextColumn::make('cbu')
                    ->label('CBU')
                    ->copyable()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('current_balance')
                    ->label('SALDO DISPONIBLE')
                    ->money('ARS')
                    ->weight('black')
                    ->size(Tables\Columns\TextColumn\TextColumnSize::Large)
                    ->color(fn ($state) => $state >= 0 ? 'success' : 'danger'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('importar_csv')
                    ->label('Subir Extracto')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->color('success')
                    ->form([
                        Forms\Components\DatePicker::make('statement_date')
                            ->label('Fecha del Extracto')
                            ->default(now())
                            ->required(),
                        Forms\Components\FileUpload::make('csv_file')
                            ->label('Archivo CSV')
                            ->acceptedFileTypes(['text/csv', 'text/plain', 'application/csv'])
                            ->storeFiles(false) // No lo guarda en disco, lo lee al vuelo
                            ->required()
                            ->helperText('El Excel guardado como CSV. Orden de columnas: 1:Fecha(YYYY-MM-DD), 2:Concepto, 3:Referencia, 4:CUIT, 5:Nombre, 6:Monto'),
                    ])
                    ->action(function (\App\Models\CompanyAccount $record, array $data) {
                        $file = $data['csv_file'];
                        $path = $file->getRealPath();
                        $handle = fopen($path, 'r');
                        
                        $statement = BankStatement::create([
                            'company_account_id' => $record->id,
                            'statement_date' => $data['statement_date'],
                            'status' => 'pending'
                        ]);

                        fgetcsv($handle); // Saltear la fila de los títulos (cabecera)
                        
                        $count = 0;
                        while (($row = fgetcsv($handle, 1000, ',')) !== false) {
                            // A veces el CSV viene separado por punto y coma. Si te pasa, cambiá el ',' por ';' arriba.
                            if (count($row) < 6) continue;
                            
                            // Magia 1: Limpiamos el CUIT para que sea solo números
                            $cuitLimpio = preg_replace('/[^0-9]/', '', $row[3]);
                            
                            // Magia 2: Buscamos si ese CUIT ya lo tenemos guardado
                            $client = Client::where('tax_id', 'LIKE', "%{$cuitLimpio}%")->first();

                            BankStatementRow::create([
                                'bank_statement_id' => $statement->id,
                                'date' => $row[0],
                                'concept' => $row[1],
                                'reference' => $row[2],
                                'cuit_origin' => $row[3],
                                'name_origin' => $row[4],
                                'amount' => (float) $row[5],
                                'client_id' => $client?->id, // ¡Si lo encontró, lo vincula solo!
                            ]);
                            $count++;
                        }
                        fclose($handle);
                        \Filament\Notifications\Notification::make()->success()->title("Extracto importado: {$count} movimientos")->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCompanyAccounts::route('/'),
            'create' => Pages\CreateCompanyAccount::route('/create'),
            'edit' => Pages\EditCompanyAccount::route('/{record}/edit'),
        ];
    }
}