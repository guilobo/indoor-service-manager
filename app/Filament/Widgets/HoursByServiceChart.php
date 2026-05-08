<?php

namespace App\Filament\Widgets;

use App\Models\Activity;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Support\Facades\DB;

class HoursByServiceChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected ?string $heading = 'Horas por serviço';

    protected function getData(): array
    {
        $query = Activity::query()
            ->select([
                DB::raw('COALESCE(services.name, "Sem serviço") as service_name'),
                DB::raw('SUM(duration_minutes) as total_minutes'),
            ])
            ->leftJoin('services', 'services.id', '=', 'activities.service_id')
            ->groupBy('service_name')
            ->orderByDesc('total_minutes');

        if (filled($this->pageFilters['client_id'] ?? null)) {
            $query->where(function ($clientQuery): void {
                $clientQuery->whereExists(function ($subquery): void {
                    $subquery
                        ->selectRaw('1')
                        ->from('contracts')
                        ->whereColumn('contracts.id', 'activities.contract_id')
                        ->where('contracts.client_id', $this->pageFilters['client_id']);
                })->orWhereExists(function ($subquery): void {
                    $subquery
                        ->selectRaw('1')
                        ->from('proposals')
                        ->whereColumn('proposals.id', 'activities.proposal_id')
                        ->where('proposals.client_id', $this->pageFilters['client_id']);
                });
            });
        }

        if (filled($this->pageFilters['contract_id'] ?? null)) {
            $query->where('activities.contract_id', $this->pageFilters['contract_id']);
        }

        if (filled($this->pageFilters['start_date'] ?? null)) {
            $query->whereDate('activities.activity_date', '>=', $this->pageFilters['start_date']);
        }

        if (filled($this->pageFilters['end_date'] ?? null)) {
            $query->whereDate('activities.activity_date', '<=', $this->pageFilters['end_date']);
        }

        $items = $query->get();

        return [
            'datasets' => [
                [
                    'label' => 'Horas',
                    'data' => $items->map(fn ($item): float => round($item->total_minutes / 60, 2))->all(),
                    'backgroundColor' => ['#f59e0b', '#0f766e', '#2563eb', '#dc2626', '#9333ea'],
                ],
            ],
            'labels' => $items->pluck('service_name')->all(),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
