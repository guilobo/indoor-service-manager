<?php

namespace App\Filament\Resources\Activities\Pages;

use App\Filament\Resources\Activities\ActivityResource;
use App\Models\Activity;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditActivity extends EditRecord
{
    protected static string $resource = ActivityResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['time_entries'] ??= [];
        $data['images'] = Activity::prepareMediaItemsForStorage($data['images'] ?? [], 'activities/images', false);
        $data['files'] = Activity::prepareMediaItemsForStorage($data['files'] ?? [], 'activities/files', false);
        $data['external_links'] = Activity::prepareExternalLinksForStorage($data['external_links'] ?? []);
        $data['time_entries'] = Activity::sortTimeEntriesDescending($data['time_entries']);

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $timeEntries = Activity::sortTimeEntriesDescending($data['time_entries'] ?? []);

        $data['images'] = Activity::prepareMediaItemsForStorage($data['images'] ?? [], 'activities/images');
        $data['files'] = Activity::prepareMediaItemsForStorage($data['files'] ?? [], 'activities/files');
        $data['external_links'] = Activity::prepareExternalLinksForStorage($data['external_links'] ?? []);
        $data['time_entries'] = $timeEntries;
        $data['duration_minutes'] = Activity::calculateDurationMinutes($timeEntries);
        $data['is_in_progress'] = collect($timeEntries)
            ->contains(fn (mixed $entry): bool => is_array($entry) && filled($entry['started_at'] ?? null) && blank($entry['ended_at'] ?? null));
        $data['activity_date'] = $data['activity_date'] ?? Activity::firstTrackedDate($timeEntries) ?? now()->toDateString();

        return $data;
    }

    protected function afterSave(): void
    {
        $this->dispatch('current-task-navigation-refresh');
    }

    public function updatedInteractsWithSchemas(string $statePath): void
    {
        parent::updatedInteractsWithSchemas($statePath);

        if ($this->isTimeEntryNotesStatePath($statePath)) {
            $this->saveTimeEntries();
        }
    }

    public function refreshTimer(): void {}

    public function saveTimeEntries(): void
    {
        $data = $this->data ?? [];
        $timeEntries = Activity::sortTimeEntriesDescending($data['time_entries'] ?? []);

        $this->data['time_entries'] = $timeEntries;

        $this->getRecord()->update([
            'time_entries' => $timeEntries,
            'duration_minutes' => Activity::calculateDurationMinutes($timeEntries),
            'is_in_progress' => collect($timeEntries)
                ->contains(fn (mixed $entry): bool => is_array($entry) && filled($entry['started_at'] ?? null) && blank($entry['ended_at'] ?? null)),
        ]);

        $this->dispatch('current-task-navigation-refresh');
    }

    protected function isTimeEntryNotesStatePath(string $statePath): bool
    {
        return (bool) preg_match('/^data\.time_entries\.[^.]+\.notes$/', $statePath);
    }

    public function toggleTimeEntry(): void
    {
        $data = $this->data ?? [];
        $timeEntries = Activity::sortTimeEntriesDescending($data['time_entries'] ?? []);
        $now = now()->seconds(0);

        $lastOpenEntryIndex = collect($timeEntries)
            ->keys()
            ->first(fn ($key) => filled($timeEntries[$key]['started_at'] ?? null) && blank($timeEntries[$key]['ended_at'] ?? null));

        if ($lastOpenEntryIndex !== null) {
            $timeEntries[$lastOpenEntryIndex]['ended_at'] = $now->toDateTimeString();
        } else {
            array_unshift($timeEntries, [
                'started_at' => $now->toDateTimeString(),
                'ended_at' => null,
                'notes' => null,
            ]);
        }

        $data['time_entries'] = $timeEntries;
        $data['reference_period'] = $data['reference_period'] ?? $now->format('Y-m');
        $data = $this->mutateFormDataBeforeSave($data);

        $this->data = $data;
        $this->form->fill($data);
        $this->getRecord()->update($data);

        Notification::make()
            ->success()
            ->title($lastOpenEntryIndex !== null ? 'Intervalo encerrado' : 'Intervalo iniciado')
            ->send();

        $this->dispatch('current-task-navigation-refresh');
    }
}
