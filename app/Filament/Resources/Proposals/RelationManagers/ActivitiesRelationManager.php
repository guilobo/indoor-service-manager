<?php

namespace App\Filament\Resources\Proposals\RelationManagers;

use App\Filament\Resources\Activities\ActivityResource;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ActivitiesRelationManager extends RelationManager
{
    protected static string $relationship = 'activities';

    public function form(Schema $schema): Schema
    {
        return ActivityResource::form($schema);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->recordUrl(fn ($record): string => ActivityResource::getUrl('edit', ['record' => $record]))
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->orderByDesc('activity_date')->orderByDesc('created_at'))
            ->columns([
                TextColumn::make('title')
                    ->label('Título')
                    ->searchable(),
                TextColumn::make('activity_date')
                    ->label('Data')
                    ->date('d/m/Y'),
                TextColumn::make('duration_minutes')
                    ->label('Tempo')
                    ->formatStateUsing(fn (int $state): string => "{$state} min"),
                TextColumn::make('service.name')
                    ->label('Serviço')
                    ->searchable(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Adicionar atividade')
                    ->createAnother(false)
                    ->fillForm([
                        'contract_id' => null,
                        'proposal_id' => $this->getOwnerRecord()->getKey(),
                        'locked_proposal_id' => $this->getOwnerRecord()->getKey(),
                        'activity_date' => now()->toDateString(),
                        'reference_period' => now()->format('Y-m'),
                    ])
                    ->mutateDataUsing(fn (array $data): array => [
                        ...$data,
                        'contract_id' => null,
                        'proposal_id' => $this->getOwnerRecord()->getKey(),
                    ])
                    ->successRedirectUrl(fn (CreateAction $action): string => ActivityResource::getUrl('edit', ['record' => $action->getRecord()])),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
