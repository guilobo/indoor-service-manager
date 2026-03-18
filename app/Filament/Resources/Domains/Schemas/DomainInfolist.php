<?php

namespace App\Filament\Resources\Domains\Schemas;

use App\Models\Domain;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class DomainInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Domínio')
                    ->schema([
                        TextEntry::make('client.name')
                            ->label('Cliente responsavel'),
                        TextEntry::make('contract.name')
                            ->label('Contrato'),
                        TextEntry::make('domain_name')
                            ->label('Domínio'),
                        TextEntry::make('status')
                            ->label('Status')
                            ->badge(),
                        TextEntry::make('hosting')
                            ->label('Hospedagem')
                            ->placeholder('-'),
                        TextEntry::make('panel_url')
                            ->label('Painel')
                            ->placeholder('-')
                            ->url(fn (?string $state): ?string => $state, shouldOpenInNewTab: true),
                        TextEntry::make('ftp_host')
                            ->label('Host FTP')
                            ->placeholder('-'),
                        TextEntry::make('ftp_user')
                            ->label('Usuário FTP')
                            ->placeholder('-'),
                        TextEntry::make('ftp_password')
                            ->label('Senha FTP')
                            ->state(fn (Domain $record): string => filled($record->ftp_password) ? 'Protegida e armazenada com criptografia' : '-'),
                        TextEntry::make('notes')
                            ->label('Observações')
                            ->placeholder('-')
                            ->columnSpanFull(),
                    ]),
                Section::make('Dados técnicos')
                    ->schema([
                        KeyValueEntry::make('credentials')
                            ->label('Credenciais')
                            ->state(fn (Domain $record): array => $record->credentials ?? []),
                        RepeatableEntry::make('email_accounts')
                            ->label('Contas de e-mail')
                            ->schema([
                                TextEntry::make('email')
                                    ->label('E-mail')
                                    ->placeholder('-'),
                                TextEntry::make('password')
                                    ->label('Senha')
                                    ->state(fn (?string $state): string => filled($state) ? 'Protegida e armazenada com criptografia' : '-'),
                            ])
                            ->contained(false),
                        KeyValueEntry::make('other_data')
                            ->label('Outros dados')
                            ->state(fn (Domain $record): array => $record->other_data ?? []),
                    ]),
            ]);
    }
}
