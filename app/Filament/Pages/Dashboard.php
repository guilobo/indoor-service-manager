<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\ContractOverview;
use App\Filament\Widgets\HoursByServiceChart;
use App\Models\Contract;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class Dashboard extends BaseDashboard
{
    use HasFiltersForm;

    protected static ?string $title = 'Painel';

    public function getWidgets(): array
    {
        return [
            ContractOverview::class,
            HoursByServiceChart::class,
        ];
    }

    public function filtersForm(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Filtros do painel')
                    ->columnSpanFull()
                    ->schema([
                        DatePicker::make('start_date')
                            ->label('Data inicial')
                            ->default(now()->startOfMonth()),
                        DatePicker::make('end_date')
                            ->label('Data final')
                            ->default(now()->endOfMonth()),
                        Select::make('contract_id')
                            ->label('Contrato')
                            ->options(Contract::query()->orderBy('name')->pluck('name', 'id')->all())
                            ->searchable()
                            ->preload(),
                    ]),
            ]);
    }

    public function getColumns(): int|array
    {
        return [
            'md' => 2,
            'xl' => 2,
        ];
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(1)
                    ->schema([
                        EmbeddedSchema::make('filtersForm')
                            ->columnSpanFull(),
                    ]),
                Grid::make($this->getColumns())
                    ->schema($this->getWidgetsSchemaComponents($this->getWidgets())),
            ]);
    }
}
