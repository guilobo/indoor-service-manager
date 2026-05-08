<?php

namespace App\Livewire;

use App\Filament\Resources\Activities\ActivityResource;
use App\Models\Activity;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

class CurrentTaskNavigation extends Component
{
    public function refreshTask(): void {}

    #[On('current-task-navigation-refresh')]
    public function reloadTask(): void {}

    public function render(): View
    {
        $task = Activity::currentTask();

        return view('livewire.current-task-navigation', [
            'task' => $task,
            'taskUrl' => $task ? ActivityResource::getUrl('edit', ['record' => $task]) : null,
            'elapsedTime' => $task ? $this->formatElapsedTime($task) : null,
            'elapsedSeconds' => $task ? Activity::openTimeEntryElapsedSeconds($task->time_entries ?? []) : null,
        ]);
    }

    protected function formatElapsedTime(Activity $activity): string
    {
        return Activity::formatElapsedSeconds(
            Activity::openTimeEntryElapsedSeconds($activity->time_entries ?? []),
        );
    }
}
