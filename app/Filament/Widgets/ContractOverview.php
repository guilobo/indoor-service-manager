<?php

namespace App\Filament\Widgets;

use App\Models\Contract;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ContractOverview extends StatsOverviewWidget
{
    use InteractsWithPageFilters;

    protected ?string $heading = 'Resumo contratual';

    protected function getStats(): array
    {
        $query = Contract::query()->withCount('domains');

        if (filled($this->pageFilters['client_id'] ?? null)) {
            $query->where('client_id', $this->pageFilters['client_id']);
        }

        if (filled($this->pageFilters['contract_id'] ?? null)) {
            $query->whereKey($this->pageFilters['contract_id']);
        }

        $contracts = $query->get();
        $startDate = filled($this->pageFilters['start_date'] ?? null) ? now()->parse($this->pageFilters['start_date']) : null;
        $endDate = filled($this->pageFilters['end_date'] ?? null) ? now()->parse($this->pageFilters['end_date']) : null;

        $usedHours = 0.0;
        $remainingHours = 0.0;
        $domains = 0;
        $monthlyValue = 0.0;

        foreach ($contracts as $contract) {
            $summary = $contract->usageSummary($startDate, $endDate);

            $usedHours += $summary['total_used_hours'];
            $remainingHours += $summary['remaining_hours'];
            $domains += $contract->total_domains;
            $monthlyValue += $contract->estimated_monthly_value;
        }

        return [
            Stat::make('Horas utilizadas', number_format($usedHours, 2, ',', '.').' h')
                ->description('Período filtrado')
                ->color('primary'),
            Stat::make('Horas disponíveis', number_format($remainingHours, 2, ',', '.').' h')
                ->description('Saldo somado dos contratos')
                ->color('success'),
            Stat::make('Domínios gerenciados', (string) $domains)
                ->description('Total de domínios vinculados')
                ->color('warning'),
            Stat::make('Valor mensal estimado', 'R$ '.number_format($monthlyValue, 2, ',', '.'))
                ->description('Horas contratadas + domínios')
                ->color('info'),
        ];
    }
}
