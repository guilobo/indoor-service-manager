<?php

namespace App\Filament\Resources\Proposals\Tables;

use App\Filament\Resources\Proposals\ProposalResource;
use App\Models\Client;
use App\ProposalStatus;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ProposalsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->recordUrl(fn ($record): string => ProposalResource::getUrl('edit', ['record' => $record]))
            ->columns([
                TextColumn::make('client.name')
                    ->label('Cliente')
                    ->searchable(),
                TextColumn::make('title')
                    ->label('Proposta')
                    ->searchable(),
                TextColumn::make('hours')
                    ->label('Horas')
                    ->numeric(decimalPlaces: 2)
                    ->sortable(),
                TextColumn::make('hourly_rate')
                    ->label('Valor/hora')
                    ->state(fn ($record): string => 'R$ '.number_format((float) $record->hourly_rate, 2, ',', '.'))
                    ->sortable(),
                TextColumn::make('estimated_value')
                    ->label('Valor total')
                    ->state(fn ($record): string => 'R$ '.number_format($record->estimated_value, 2, ',', '.')),
                TextColumn::make('activities_count')
                    ->label('Atividades')
                    ->badge()
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->label('Atualizada em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Filter::make('cliente')
                    ->label('Cliente')
                    ->schema([
                        Select::make('client_id')
                            ->label('Cliente')
                            ->options(Client::query()->orderBy('name')->pluck('name', 'id')->all())
                            ->searchable()
                            ->preload(),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query->when($data['client_id'] ?? null, fn (Builder $builder, $clientId): Builder => $builder->where('client_id', $clientId))),
                Filter::make('status')
                    ->label('Status')
                    ->schema([
                        Select::make('status')
                            ->label('Status')
                            ->options(collect(ProposalStatus::cases())->mapWithKeys(fn (ProposalStatus $status): array => [$status->value => $status->getLabel()])->all()),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query->when($data['status'] ?? null, fn (Builder $builder, $status): Builder => $builder->where('status', $status))),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('updated_at', 'desc');
    }
}
