<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\ContractOverview;
use App\Filament\Widgets\HoursByServiceChart;
use App\Filament\Widgets\ReportsActivitiesTable;
use App\Filament\Widgets\ReportsClientHoursTable;
use App\Filament\Widgets\ReportsContractUsageTable;
use App\Models\Client;
use App\Models\Contract;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class Reports extends BaseDashboard
{
    use HasFiltersForm;

    protected static string $routePath = '/reports';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBarSquare;

    protected static ?string $navigationLabel = 'Relatorios';

    protected static string|\UnitEnum|null $navigationGroup = 'Analises';

    protected static ?int $navigationSort = 1;

    protected static ?string $title = 'Relatorios';

    public function filtersForm(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Filtros')
                    ->columnSpanFull()
                    ->schema([
                        Select::make('client_id')
                            ->label('Cliente')
                            ->options(Client::query()->orderBy('name')->pluck('name', 'id')->all())
                            ->searchable()
                            ->preload(),
                        Select::make('contract_id')
                            ->label('Contrato')
                            ->options(fn (callable $get): array => Contract::query()
                                ->when($get('client_id'), fn ($query, $clientId) => $query->where('client_id', $clientId))
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->all())
                            ->searchable()
                            ->preload(),
                        DatePicker::make('start_date')
                            ->label('Data inicial')
                            ->default(now()->startOfMonth()),
                        DatePicker::make('end_date')
                            ->label('Data final')
                            ->default(now()->endOfMonth()),
                    ]),
            ]);
    }

    public function getWidgets(): array
    {
        return [
            ContractOverview::class,
            HoursByServiceChart::class,
            ReportsClientHoursTable::class,
            ReportsContractUsageTable::class,
            ReportsActivitiesTable::class,
        ];
    }

    public function getColumns(): int|array
    {
        return [
            'md' => 2,
            'xl' => 2,
        ];
    }
}
