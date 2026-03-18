<?php

namespace App\Filament\Resources\Contracts\Schemas;

use App\ContractStatus;
use App\Models\Client;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
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
                    ->collapsible()
                    ->persistCollapsed()
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Select::make('client_id')
                                    ->label('Cliente')
                                    ->options(Client::query()->orderBy('name')->pluck('name', 'id')->all())
                                    ->searchable()
                                    ->preload()
                                    ->createOptionForm([
                                        Grid::make(2)
                                            ->schema([
                                                TextInput::make('name')
                                                    ->label('Nome')
                                                    ->required()
                                                    ->maxLength(255),
                                                TextInput::make('company_name')
                                                    ->label('Empresa')
                                                    ->maxLength(255),
                                                TextInput::make('document')
                                                    ->label('CPF/CNPJ')
                                                    ->required()
                                                    ->unique(Client::class, 'document')
                                                    ->maxLength(20),
                                                TextInput::make('email')
                                                    ->label('E-mail')
                                                    ->email()
                                                    ->required()
                                                    ->unique(Client::class, 'email')
                                                    ->maxLength(255),
                                                TextInput::make('phone')
                                                    ->label('Telefone')
                                                    ->tel()
                                                    ->required()
                                                    ->maxLength(30),
                                                TextInput::make('whatsapp')
                                                    ->label('WhatsApp')
                                                    ->tel()
                                                    ->maxLength(30),
                                            ]),
                                        Textarea::make('address')
                                            ->label('Endereco')
                                            ->rows(3)
                                            ->columnSpanFull(),
                                        Textarea::make('notes')
                                            ->label('Observacoes')
                                            ->rows(4)
                                            ->columnSpanFull(),
                                    ])
                                    ->createOptionUsing(fn (array $data): int => Client::query()->create($data)->getKey())
                                    ->required(),
                                TextInput::make('name')
                                    ->label('Nome do contrato')
                                    ->required()
                                    ->maxLength(255),
                                DatePicker::make('start_date')
                                    ->label('Inicio da vigencia')
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
                                    ->label('Valor por dominio')
                                    ->numeric()
                                    ->prefix('R$')
                                    ->default(null),
                                Select::make('status')
                                    ->label('Status')
                                    ->options(ContractStatus::class)
                                    ->required(),
                            ]),
                        Textarea::make('description')
                            ->label('Descricao')
                            ->rows(4)
                            ->columnSpanFull(),
                        Textarea::make('notes')
                            ->label('Observacoes')
                            ->rows(4)
                            ->columnSpanFull(),
                        FileUpload::make('contract_file')
                            ->label('Arquivo do contrato')
                            ->helperText('Opcional')
                            ->directory('contracts/files')
                            ->disk('public')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
