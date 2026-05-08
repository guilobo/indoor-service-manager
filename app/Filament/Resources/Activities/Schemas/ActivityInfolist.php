<?php

namespace App\Filament\Resources\Activities\Schemas;

use App\Models\Activity;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ActivityInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Atividade')
                    ->schema([
                        TextEntry::make('contract.name')
                            ->label('Contrato')
                            ->placeholder('-'),
                        TextEntry::make('proposal.title')
                            ->label('Proposta')
                            ->placeholder('-'),
                        TextEntry::make('service.name')
                            ->label('Serviço')
                            ->placeholder('-'),
                        TextEntry::make('title')
                            ->label('Título'),
                        TextEntry::make('activity_date')
                            ->label('Data')
                            ->date('d/m/Y'),
                        TextEntry::make('duration_minutes')
                            ->label('Duração (min)')
                            ->numeric(),
                        TextEntry::make('reference_period')
                            ->label('Período de referência')
                            ->placeholder('-'),
                        TextEntry::make('description')
                            ->label('Descrição')
                            ->html()
                            ->placeholder('-')
                            ->columnSpanFull(),
                        TextEntry::make('images')
                            ->label('Imagens')
                            ->state(fn (Activity $record): string => blank($record->images_list) ? '-' : collect($record->images_list)->map(fn (array $item): string => "{$item['title']}: {$item['url']}")->implode(', '))
                            ->columnSpanFull(),
                        TextEntry::make('files')
                            ->label('Arquivos')
                            ->state(fn (Activity $record): string => blank($record->files_list) ? '-' : collect($record->files_list)->map(fn (array $item): string => "{$item['title']}: {$item['url']}")->implode(', '))
                            ->columnSpanFull(),
                        TextEntry::make('external_links_list')
                            ->label('Links externos')
                            ->state(fn (Activity $record): string => blank($record->external_links_list) ? '-' : implode(', ', $record->external_links_list))
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
