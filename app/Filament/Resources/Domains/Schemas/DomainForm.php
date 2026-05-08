<?php

namespace App\Filament\Resources\Domains\Schemas;

use App\DomainAccessType;
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
                Section::make('Domínio')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Select::make('client_id')
                                    ->label('Cliente responsável')
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
                                    ->label('Domínio')
                                    ->required()
                                    ->maxLength(255),
                                Select::make('status')
                                    ->label('Status')
                                    ->options(DomainStatus::class)
                                    ->required(),
                                Select::make('access_type')
                                    ->label('Tipo de acesso')
                                    ->options(DomainAccessType::class)
                                    ->default(DomainAccessType::Ftp)
                                    ->required(),
                                TextInput::make('access_port')
                                    ->label('Porta')
                                    ->numeric()
                                    ->minValue(1)
                                    ->maxValue(65535)
                                    ->placeholder('Automática conforme o tipo'),
                                TextInput::make('access_root_path')
                                    ->label('Diretório raiz')
                                    ->helperText('Raiz real do acesso remoto. Ex.: /home/dh_9m4upu')
                                    ->placeholder('/home/usuario')
                                    ->maxLength(255),
                                TextInput::make('access_start_path')
                                    ->label('Pasta inicial')
                                    ->helperText('Opcional. Caminho relativo dentro da raiz para abrir direto nessa pasta.')
                                    ->placeholder('contribuicao.sinregas.com.br')
                                    ->maxLength(255),
                                TextInput::make('hosting')
                                    ->label('Hospedagem')
                                    ->maxLength(255),
                                TextInput::make('ftp_host')
                                    ->label('Host do acesso')
                                    ->maxLength(255),
                                TextInput::make('ftp_user')
                                    ->label('Usuário de acesso')
                                    ->maxLength(255),
                                TextInput::make('ftp_password')
                                    ->label('Senha de acesso')
                                    ->password()
                                    ->revealable(),
                                TextInput::make('panel_url')
                                    ->label('URL do painel')
                                    ->url()
                                    ->maxLength(255),
                            ]),
                        Textarea::make('notes')
                            ->label('Observações')
                            ->rows(4)
                            ->columnSpanFull(),
                    ]),
                Section::make('Dados técnicos')
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
