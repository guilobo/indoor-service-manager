<?php

namespace App\Filament\Resources\Proposals\Schemas;

use App\Models\Client;
use App\ProposalStatus;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
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
                                TextInput::make('hours')
                                    ->label('Horas')
                                    ->numeric()
                                    ->required(),
                                TextInput::make('hourly_rate')
                                    ->label('Valor por hora')
                                    ->numeric()
                                    ->prefix('R$')
                                    ->required(),
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
}
