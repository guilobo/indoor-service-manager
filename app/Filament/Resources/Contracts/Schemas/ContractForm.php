<?php

namespace App\Filament\Resources\Contracts\Schemas;

use App\ContractStatus;
use App\Models\Client;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ContractForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Contrato')
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Select::make('client_id')
                                    ->label('Cliente')
                                    ->options(Client::query()->orderBy('name')->pluck('name', 'id')->all())
                                    ->searchable()
                                    ->preload()
                                    ->required(),
                                TextInput::make('name')
                                    ->label('Nome do contrato')
                                    ->required()
                                    ->maxLength(255),
                                DatePicker::make('start_date')
                                    ->label('Início da vigência')
                                    ->required(),
                                DatePicker::make('end_date')
                                    ->label('Fim da vigencia')
                                    ->afterOrEqual('start_date'),
                                TextInput::make('monthly_hours')
                                    ->label('Horas mensais')
                                    ->numeric(),
                                TextInput::make('hourly_rate')
                                    ->label('Valor por hora')
                                    ->numeric()
                                    ->prefix('R$'),
                                TextInput::make('domain_rate')
                                    ->label('Valor por domínio')
                                    ->numeric()
                                    ->prefix('R$')
                                    ->default(null),
                                Select::make('status')
                                    ->label('Status')
                                    ->options(ContractStatus::class)
                                    ->required(),
                            ]),
                        Textarea::make('description')
                            ->label('Descrição')
                            ->rows(4)
                            ->columnSpanFull(),
                        Textarea::make('notes')
                            ->label('Observações')
                            ->rows(4)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
