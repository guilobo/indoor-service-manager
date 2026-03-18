<?php

namespace App\Filament\Resources\Activities\Tables;

use App\Filament\Resources\Activities\ActivityResource;
use App\Models\Client;
use App\Models\Contract;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ActivitiesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->recordUrl(fn ($record): string => ActivityResource::getUrl('edit', ['record' => $record]))
            ->columns([
                TextColumn::make('contract.client.name')
                    ->label('Cliente')
                    ->searchable(),
                TextColumn::make('contract.name')
                    ->label('Contrato')
                    ->searchable(),
                TextColumn::make('service.name')
                    ->label('Serviço')
                    ->searchable(),
                TextColumn::make('title')
                    ->label('Título')
                    ->searchable(),
                TextColumn::make('activity_date')
                    ->label('Data')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('duration_minutes')
                    ->label('Tempo')
                    ->formatStateUsing(fn (int $state): string => "{$state} min")
                    ->summarize([
                        Sum::make()
                            ->label('Total')
                            ->formatStateUsing(fn ($state): string => number_format(((int) $state) / 60, 2, ',', '.').' h'),
                    ])
                    ->sortable(),
                TextColumn::make('reference_period')
                    ->label('Período')
                    ->searchable(),
            ])
            ->filters([
                Filter::make('periodo')
                    ->label('Período')
                    ->schema([
                        DatePicker::make('start_date')
                            ->label('De'),
                        DatePicker::make('end_date')
                            ->label('Até'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['start_date'] ?? null, fn (Builder $builder, $date): Builder => $builder->whereDate('activity_date', '>=', $date))
                            ->when($data['end_date'] ?? null, fn (Builder $builder, $date): Builder => $builder->whereDate('activity_date', '<=', $date));
                    }),
                Filter::make('cliente')
                    ->label('Cliente')
                    ->schema([
                        Select::make('client_id')
                            ->label('Cliente')
                            ->options(Client::query()->orderBy('name')->pluck('name', 'id')->all())
                            ->searchable()
                            ->preload(),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query->when($data['client_id'] ?? null, fn (Builder $builder, $clientId): Builder => $builder->whereHas('contract', fn (Builder $contractQuery): Builder => $contractQuery->where('client_id', $clientId)))),
                Filter::make('contrato')
                    ->label('Contrato')
                    ->schema([
                        Select::make('contract_id')
                            ->label('Contrato')
                            ->options(Contract::query()->orderBy('name')->pluck('name', 'id')->all())
                            ->searchable()
                            ->preload(),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query->when($data['contract_id'] ?? null, fn (Builder $builder, $contractId): Builder => $builder->where('contract_id', $contractId))),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('activity_date', 'desc');
    }
}
