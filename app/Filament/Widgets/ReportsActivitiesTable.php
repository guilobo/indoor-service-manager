<?php

namespace App\Filament\Widgets;

use App\Filament\Exports\ActivityExporter;
use App\Filament\Resources\Activities\ActivityResource;
use App\Models\Activity;
use Filament\Actions\ExportAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class ReportsActivitiesTable extends TableWidget
{
    use InteractsWithPageFilters;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Atividades no periodo')
            ->recordUrl(fn (Activity $record): string => ActivityResource::getUrl('edit', ['record' => $record]))
            ->query(
                Activity::query()
                    ->with(['contract.client', 'proposal.client', 'service'])
                    ->forClient($this->pageFilters['client_id'] ?? null)
                    ->when($this->pageFilters['contract_id'] ?? null, fn (Builder $query, $contractId): Builder => $query->where('contract_id', $contractId))
                    ->when($this->pageFilters['start_date'] ?? null, fn (Builder $query, $startDate): Builder => $query->whereDate('activity_date', '>=', $startDate))
                    ->when($this->pageFilters['end_date'] ?? null, fn (Builder $query, $endDate): Builder => $query->whereDate('activity_date', '<=', $endDate))
            )
            ->headerActions([
                ExportAction::make('exportReport')
                    ->label('Exportar relatorio')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->exporter(ActivityExporter::class)
                    ->columnMapping(false)
                    ->options(fn (): array => [
                        'start_date' => $this->pageFilters['start_date'] ?? null,
                        'end_date' => $this->pageFilters['end_date'] ?? null,
                        'client_id' => $this->pageFilters['client_id'] ?? null,
                        'contract_id' => $this->pageFilters['contract_id'] ?? null,
                    ])
                    ->modifyQueryUsing(function (Builder $query): Builder {
                        return $query
                            ->with(['contract.client', 'proposal.client', 'service'])
                            ->forClient($this->pageFilters['client_id'] ?? null)
                            ->when($this->pageFilters['contract_id'] ?? null, fn (Builder $exportQuery, $contractId): Builder => $exportQuery->where('contract_id', $contractId))
                            ->when($this->pageFilters['start_date'] ?? null, fn (Builder $exportQuery, $startDate): Builder => $exportQuery->whereDate('activity_date', '>=', $startDate))
                            ->when($this->pageFilters['end_date'] ?? null, fn (Builder $exportQuery, $endDate): Builder => $exportQuery->whereDate('activity_date', '<=', $endDate));
                    }),
            ])
            ->columns([
                TextColumn::make('activity_date')
                    ->label('Data')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('client_name')
                    ->label('Cliente')
                    ->state(fn (Activity $record): string => $record->client_name),
                TextColumn::make('source_label')
                    ->label('Origem')
                    ->badge()
                    ->state(fn (Activity $record): string => $record->source_label),
                TextColumn::make('source_name')
                    ->label('Contrato/Proposta')
                    ->state(fn (Activity $record): string => $record->source_name),
                TextColumn::make('service.name')
                    ->label('Servico')
                    ->placeholder('-'),
                TextColumn::make('title')
                    ->label('Titulo')
                    ->searchable(),
                TextColumn::make('duration_minutes')
                    ->label('Tempo')
                    ->formatStateUsing(fn (int $state): string => "{$state} min"),
            ])
            ->defaultSort('activity_date', 'desc');
    }
}
