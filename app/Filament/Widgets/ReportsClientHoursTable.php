<?php

namespace App\Filament\Widgets;

use App\Models\Activity;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ReportsClientHoursTable extends TableWidget
{
    use InteractsWithPageFilters;

    protected int|string|array $columnSpan = 1;

    public function table(Table $table): Table
    {
        return $table
            ->heading('Horas por cliente')
            ->query($this->getTableQuery())
            ->columns([
                TextColumn::make('client_name')
                    ->label('Cliente'),
                TextColumn::make('total_hours')
                    ->label('Horas')
                    ->state(fn ($record): string => number_format((float) $record->total_hours, 2, ',', '.').' h'),
            ])
            ->paginated(false)
            ->defaultSort('total_hours', 'desc');
    }

    protected function getTableQuery(): Builder
    {
        return Activity::query()
            ->select([
                DB::raw('MIN(activities.id) as id'),
                DB::raw('clients.name as client_name'),
                DB::raw('ROUND(SUM(activities.duration_minutes) / 60, 2) as total_hours'),
            ])
            ->join('contracts', 'contracts.id', '=', 'activities.contract_id')
            ->join('clients', 'clients.id', '=', 'contracts.client_id')
            ->when($this->pageFilters['client_id'] ?? null, fn (Builder $query, $clientId): Builder => $query->where('clients.id', $clientId))
            ->when($this->pageFilters['contract_id'] ?? null, fn (Builder $query, $contractId): Builder => $query->where('contracts.id', $contractId))
            ->when($this->pageFilters['start_date'] ?? null, fn (Builder $query, $startDate): Builder => $query->whereDate('activities.activity_date', '>=', $startDate))
            ->when($this->pageFilters['end_date'] ?? null, fn (Builder $query, $endDate): Builder => $query->whereDate('activities.activity_date', '<=', $endDate))
            ->groupBy('clients.name');
    }
}
