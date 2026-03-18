<?php

namespace App\Filament\Resources\Contracts\Tables;

use App\ContractStatus;
use App\Filament\Resources\Contracts\ContractResource;
use App\Models\Client;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ContractsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->recordUrl(fn ($record): string => ContractResource::getUrl('edit', ['record' => $record]))
            ->columns([
                TextColumn::make('client.name')
                    ->label('Cliente')
                    ->searchable(),
                TextColumn::make('name')
                    ->label('Contrato')
                    ->searchable(),
                TextColumn::make('start_date')
                    ->label('Início')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('end_date')
                    ->label('Fim')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('monthly_hours')
                    ->label('Horas/mês')
                    ->numeric(decimalPlaces: 2)
                    ->sortable(),
                TextColumn::make('domains_count')
                    ->label('Domínios')
                    ->badge()
                    ->sortable(),
                TextColumn::make('estimated_monthly_value')
                    ->label('Valor estimado')
                    ->state(fn ($record): string => 'R$ '.number_format($record->estimated_monthly_value, 2, ',', '.'))
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->sortable(),
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
                            ->when($data['start_date'] ?? null, fn (Builder $builder, $date): Builder => $builder->where(function (Builder $endDateQuery) use ($date): void {
                                $endDateQuery
                                    ->whereNull('end_date')
                                    ->orWhereDate('end_date', '>=', $date);
                            }))
                            ->when($data['end_date'] ?? null, fn (Builder $builder, $date): Builder => $builder->whereDate('start_date', '<=', $date));
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
                    ->query(fn (Builder $query, array $data): Builder => $query->when($data['client_id'] ?? null, fn (Builder $builder, $clientId): Builder => $builder->where('client_id', $clientId))),
                Filter::make('status')
                    ->label('Status')
                    ->schema([
                        Select::make('status')
                            ->label('Status')
                            ->options(collect(ContractStatus::cases())->mapWithKeys(fn (ContractStatus $status): array => [$status->value => $status->getLabel()])->all()),
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
            ->defaultSort('end_date', 'desc');
    }
}
