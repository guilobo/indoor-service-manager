<?php

namespace App\Filament\Widgets;

use App\Models\Contract;
use Carbon\Carbon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\TableWidget;

class ReportsContractUsageTable extends TableWidget
{
    use InteractsWithPageFilters;

    protected int|string|array $columnSpan = 1;

    public function table(Table $table): Table
    {
        return $table
            ->heading('Uso por contrato')
            ->query(
                Contract::query()
                    ->with(['client'])
                    ->withCount('domains')
                    ->when($this->pageFilters['client_id'] ?? null, fn ($query, $clientId) => $query->where('client_id', $clientId))
                    ->when($this->pageFilters['contract_id'] ?? null, fn ($query, $contractId) => $query->whereKey($contractId))
            )
            ->columns([
                TextColumn::make('client.name')
                    ->label('Cliente'),
                TextColumn::make('name')
                    ->label('Contrato'),
                TextColumn::make('used_hours')
                    ->label('Usadas')
                    ->state(fn (Contract $record): string => number_format($record->usageSummary($this->getStartDate(), $this->getEndDate())['total_used_hours'], 2, ',', '.').' h'),
                TextColumn::make('remaining_hours')
                    ->label('Saldo')
                    ->state(fn (Contract $record): string => number_format($record->usageSummary($this->getStartDate(), $this->getEndDate())['remaining_hours'], 2, ',', '.').' h'),
                TextColumn::make('estimated_monthly_value')
                    ->label('Valor')
                    ->state(fn (Contract $record): string => 'R$ '.number_format($record->estimated_monthly_value, 2, ',', '.')),
            ])
            ->paginated(false)
            ->defaultSort('name');
    }

    protected function getStartDate(): ?Carbon
    {
        return filled($this->pageFilters['start_date'] ?? null) ? now()->parse($this->pageFilters['start_date']) : null;
    }

    protected function getEndDate(): ?Carbon
    {
        return filled($this->pageFilters['end_date'] ?? null) ? now()->parse($this->pageFilters['end_date']) : null;
    }
}
