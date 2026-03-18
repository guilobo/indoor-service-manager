<?php

namespace App\Filament\Resources\Services\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ServiceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Serviço')
                    ->schema([
                        TextInput::make('name')
                            ->label('Nome')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),
                        Textarea::make('description')
                            ->label('Descrição')
                            ->rows(4)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
