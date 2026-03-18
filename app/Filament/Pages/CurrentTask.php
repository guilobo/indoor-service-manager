<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Activities\ActivityResource;
use App\Models\Activity;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class CurrentTask extends Page
{
    protected static ?string $title = 'Tarefa em andamento';

    protected static ?string $navigationLabel = 'Tarefa em andamento';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedBolt;

    protected static string|\UnitEnum|null $navigationGroup = 'Operacao';

    protected static ?int $navigationSort = -1;

    protected static string $routePath = 'current-task';

    protected string $view = 'filament-panels::pages.page';

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public function mount(): void
    {
        $activity = Activity::currentTask();

        $this->redirect(
            $activity
                ? ActivityResource::getUrl('edit', ['record' => $activity])
                : ActivityResource::getUrl()
        );
    }
}
