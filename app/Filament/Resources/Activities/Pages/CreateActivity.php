<?php

namespace App\Filament\Resources\Activities\Pages;

use App\Filament\Resources\Activities\ActivityResource;
use App\Models\Activity;
use Filament\Resources\Pages\CreateRecord;

class CreateActivity extends CreateRecord
{
    protected static string $resource = ActivityResource::class;

    protected function getRedirectUrl(): string
    {
        return ActivityResource::getUrl('edit', ['record' => $this->getRecord()]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $timeEntries = Activity::sortTimeEntriesDescending($data['time_entries'] ?? []);

        $data['time_entries'] = $timeEntries;
        $data['duration_minutes'] = Activity::calculateDurationMinutes($timeEntries);
        $data['is_in_progress'] = collect($timeEntries)
            ->contains(fn (mixed $entry): bool => is_array($entry) && filled($entry['started_at'] ?? null) && blank($entry['ended_at'] ?? null));
        $data['activity_date'] = $data['activity_date'] ?? Activity::firstTrackedDate($timeEntries) ?? now()->toDateString();

        return $data;
    }
}
