<?php

namespace App\Filament\Resources\Clients\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ClientForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Dados do cliente')
                    ->columnSpanFull()
                    ->schema([
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
                                    ->unique(ignoreRecord: true)
                                    ->maxLength(20),
                                TextInput::make('email')
                                    ->label('E-mail')
                                    ->email()
                                    ->required()
                                    ->unique(ignoreRecord: true)
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
                    ]),
            ]);
    }
}
