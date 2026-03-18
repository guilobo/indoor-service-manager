<?php

namespace App\Filament\Resources\Domains\Schemas;

use App\DomainStatus;
use App\Models\Client;
use App\Models\Contract;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class DomainForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Dominio')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Select::make('client_id')
                                    ->label('Cliente responsavel')
                                    ->options(Client::query()->orderBy('name')->pluck('name', 'id')->all())
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->live(),
                                Select::make('contract_id')
                                    ->label('Contrato')
                                    ->options(fn (callable $get): array => Contract::query()
                                        ->when($get('client_id'), fn ($query, $clientId) => $query->where('client_id', $clientId))
                                        ->orderBy('name')
                                        ->pluck('name', 'id')
                                        ->all())
                                    ->searchable()
                                    ->preload()
                                    ->helperText('Opcional'),
                                TextInput::make('domain_name')
                                    ->label('Dominio')
                                    ->required()
                                    ->maxLength(255),
                                Select::make('status')
                                    ->label('Status')
                                    ->options(DomainStatus::class)
                                    ->required(),
                                TextInput::make('hosting')
                                    ->label('Hospedagem')
                                    ->maxLength(255),
                                TextInput::make('ftp_host')
                                    ->label('Host FTP')
                                    ->maxLength(255),
                                TextInput::make('ftp_user')
                                    ->label('Usuario FTP')
                                    ->maxLength(255),
                                TextInput::make('ftp_password')
                                    ->label('Senha FTP')
                                    ->password()
                                    ->revealable(),
                                TextInput::make('panel_url')
                                    ->label('URL do painel')
                                    ->url()
                                    ->maxLength(255),
                            ]),
                        Textarea::make('notes')
                            ->label('Observacoes')
                            ->rows(4)
                            ->columnSpanFull(),
                    ]),
                Section::make('Dados tecnicos')
                    ->schema([
                        KeyValue::make('credentials')
                            ->label('Credenciais')
                            ->keyLabel('Campo')
                            ->valueLabel('Valor')
                            ->columnSpanFull(),
                        Repeater::make('email_accounts')
                            ->label('Contas de e-mail')
                            ->default([])
                            ->schema([
                                TextInput::make('email')
                                    ->label('E-mail')
                                    ->email()
                                    ->required(),
                                TextInput::make('password')
                                    ->label('Senha')
                                    ->password()
                                    ->revealable(),
                            ])
                            ->grid(2)
                            ->columnSpanFull(),
                        KeyValue::make('other_data')
                            ->label('Outros dados')
                            ->keyLabel('Chave')
                            ->valueLabel('Valor')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
