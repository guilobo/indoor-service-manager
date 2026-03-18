<?php

namespace App\Filament\Resources\Contracts\RelationManagers;

use App\Filament\Resources\Domains\DomainResource;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class DomainsRelationManager extends RelationManager
{
    protected static string $relationship = 'domains';

    public function form(Schema $schema): Schema
    {
        return DomainResource::form($schema);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('domain_name')
            ->recordUrl(fn ($record): string => DomainResource::getUrl('edit', ['record' => $record]))
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->latest('created_at'))
            ->columns([
                TextColumn::make('domain_name')
                    ->label('Dominio')
                    ->searchable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge(),
                TextColumn::make('hosting')
                    ->label('Hospedagem')
                    ->searchable(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Adicionar dominio')
                    ->createAnother(false)
                    ->fillForm([
                        'client_id' => $this->getOwnerRecord()->client_id,
                        'contract_id' => $this->getOwnerRecord()->getKey(),
                    ])
                    ->mutateDataUsing(fn (array $data): array => [
                        ...$data,
                        'client_id' => $this->getOwnerRecord()->client_id,
                        'contract_id' => $this->getOwnerRecord()->getKey(),
                    ])
                    ->successRedirectUrl(fn (CreateAction $action): string => DomainResource::getUrl('edit', ['record' => $action->getRecord()])),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
