<?php

namespace App\Filament\Resources\Proposals\Schemas;

use App\Models\Client;
use App\ProposalBillingType;
use App\ProposalStatus;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ProposalForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Proposta')
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
                                            ->label('Endereço')
                                            ->rows(3)
                                            ->columnSpanFull(),
                                        Textarea::make('notes')
                                            ->label('Observações')
                                            ->rows(4)
                                            ->columnSpanFull(),
                                    ])
                                    ->createOptionUsing(fn (array $data): int => Client::query()->create($data)->getKey())
                                    ->required(),
                                TextInput::make('title')
                                    ->label('Título da proposta')
                                    ->required()
                                    ->maxLength(255),
                                ToggleButtons::make('billing_type')
                                    ->label('Tipo de cobranca')
                                    ->options([
                                        ProposalBillingType::Hourly->value => ProposalBillingType::Hourly->getLabel(),
                                        ProposalBillingType::Fixed->value => ProposalBillingType::Fixed->getLabel(),
                                    ])
                                    ->icons([
                                        ProposalBillingType::Hourly->value => 'heroicon-o-clock',
                                        ProposalBillingType::Fixed->value => 'heroicon-o-banknotes',
                                    ])
                                    ->colors([
                                        ProposalBillingType::Hourly->value => 'info',
                                        ProposalBillingType::Fixed->value => 'success',
                                    ])
                                    ->default(ProposalBillingType::Hourly->value)
                                    ->grouped()
                                    ->inline()
                                    ->live()
                                    ->afterStateUpdated(function (mixed $state, callable $set): void {
                                        if (self::isFixedBillingType($state)) {
                                            $set('hours', null);
                                            $set('hourly_rate', null);

                                            return;
                                        }

                                        $set('fixed_value', null);
                                    })
                                    ->required(),
                                TextInput::make('hours')
                                    ->label('Horas')
                                    ->numeric()
                                    ->required(fn (callable $get): bool => self::isHourlyBillingType($get('billing_type')))
                                    ->visible(fn (callable $get): bool => self::isHourlyBillingType($get('billing_type'))),
                                TextInput::make('hourly_rate')
                                    ->label('Valor por hora')
                                    ->numeric()
                                    ->prefix('R$')
                                    ->required(fn (callable $get): bool => self::isHourlyBillingType($get('billing_type')))
                                    ->visible(fn (callable $get): bool => self::isHourlyBillingType($get('billing_type'))),
                                TextInput::make('fixed_value')
                                    ->label('Valor da proposta')
                                    ->numeric()
                                    ->prefix('R$')
                                    ->required(fn (callable $get): bool => self::isFixedBillingType($get('billing_type')))
                                    ->visible(fn (callable $get): bool => self::isFixedBillingType($get('billing_type'))),
                                Select::make('status')
                                    ->label('Status')
                                    ->options(ProposalStatus::class)
                                    ->default(ProposalStatus::Pending)
                                    ->required(),
                            ]),
                        Textarea::make('description')
                            ->label('Descrição')
                            ->rows(4)
                            ->columnSpanFull(),
                        FileUpload::make('proposal_file')
                            ->label('Arquivo da proposta')
                            ->directory('proposals/files')
                            ->disk('public')
                            ->fetchFileInformation(false)
                            ->columnSpanFull(),
                        Repeater::make('attachments')
                            ->label('Anexos adicionais')
                            ->default([])
                            ->schema([
                                TextInput::make('title')
                                    ->label('Título')
                                    ->required()
                                    ->maxLength(255),
                                FileUpload::make('path')
                                    ->label('Arquivo')
                                    ->directory('proposals/attachments')
                                    ->disk('public')
                                    ->fetchFileInformation(false)
                                    ->required(),
                            ])
                            ->columns(2)
                            ->columnSpanFull(),
                        Textarea::make('notes')
                            ->label('Observações')
                            ->rows(4)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function isHourlyBillingType(mixed $state): bool
    {
        return self::billingTypeValue($state) === ProposalBillingType::Hourly->value;
    }

    public static function isFixedBillingType(mixed $state): bool
    {
        return self::billingTypeValue($state) === ProposalBillingType::Fixed->value;
    }

    protected static function billingTypeValue(mixed $state): string
    {
        if ($state instanceof ProposalBillingType) {
            return $state->value;
        }

        if (blank($state)) {
            return ProposalBillingType::Hourly->value;
        }

        return (string) $state;
    }
}
