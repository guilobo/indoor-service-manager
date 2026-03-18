<?php

namespace App\Filament\Resources\Contracts\RelationManagers;

use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DomainsRelationManager extends RelationManager
{
    protected static string $relationship = 'domains';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('domain_name')
            ->columns([
                TextColumn::make('domain_name')
                    ->label('Domínio')
                    ->searchable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge(),
                TextColumn::make('hosting')
                    ->label('Hospedagem')
                    ->searchable(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ]);
    }
}
