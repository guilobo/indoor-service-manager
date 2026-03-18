<?php

namespace App\Filament\Resources\Clients\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ClientInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Cliente')
                    ->schema([
                        TextEntry::make('name')
                            ->label('Nome'),
                        TextEntry::make('company_name')
                            ->label('Empresa')
                            ->placeholder('-'),
                        TextEntry::make('document')
                            ->label('CPF/CNPJ'),
                        TextEntry::make('email')
                            ->label('E-mail'),
                        TextEntry::make('phone')
                            ->label('Telefone'),
                        TextEntry::make('whatsapp')
                            ->label('WhatsApp')
                            ->placeholder('-'),
                        TextEntry::make('address')
                            ->label('Endereço')
                            ->placeholder('-')
                            ->columnSpanFull(),
                        TextEntry::make('notes')
                            ->label('Observações')
                            ->placeholder('-')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
