<?php

namespace App\Filament\Resources\Services\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ServiceInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Serviço')
                    ->schema([
                        TextEntry::make('name')
                            ->label('Nome'),
                        TextEntry::make('description')
                            ->label('Descrição')
                            ->placeholder('-')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
