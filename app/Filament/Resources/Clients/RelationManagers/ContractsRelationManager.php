<?php

namespace App\Filament\Resources\Clients\RelationManagers;

use App\Filament\Resources\Contracts\ContractResource;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ContractsRelationManager extends RelationManager
{
    protected static string $relationship = 'contracts';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->recordUrl(fn ($record): string => ContractResource::getUrl('edit', ['record' => $record]))
            ->columns([
                TextColumn::make('name')
                    ->label('Contrato')
                    ->searchable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge(),
                TextColumn::make('monthly_hours')
                    ->label('Horas/mês')
                    ->numeric(decimalPlaces: 2),
                TextColumn::make('end_date')
                    ->label('Vigência até')
                    ->date('d/m/Y')
                    ->searchable(),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
