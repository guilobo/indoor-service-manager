<?php

namespace App\Livewire;

use App\Filament\Resources\Activities\ActivityResource;
use App\Models\Activity;
use Carbon\Carbon;
use Livewire\Attributes\On;
use Livewire\Component;

class CurrentTaskNavigation extends Component
{
    public function refreshTask(): void {}

    #[On('current-task-navigation-refresh')]
    public function reloadTask(): void {}

    public function render()
    {
        $task = Activity::currentTask();

        return view('livewire.current-task-navigation', [
            'task' => $task,
            'taskUrl' => $task ? ActivityResource::getUrl('edit', ['record' => $task]) : null,
            'elapsedTime' => $task ? $this->formatElapsedTime($task) : null,
        ]);
    }

    protected function formatElapsedTime(Activity $activity): string
    {
        $openEntry = collect($activity->time_entries)
            ->filter(fn (mixed $entry): bool => is_array($entry) && filled($entry['started_at'] ?? null) && blank($entry['ended_at'] ?? null))
            ->last();

        if (! is_array($openEntry) || blank($openEntry['started_at'] ?? null)) {
            return '00:00:00';
        }

        $startedAt = Carbon::parse($openEntry['started_at']);
        $seconds = max($startedAt->diffInSeconds(now()), 0);

        return gmdate('H:i:s', $seconds);
    }
}
