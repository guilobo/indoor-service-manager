<?php

namespace App\Models;

use Carbon\Carbon;
use Database\Factories\ActivityFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Activity extends Model
{
    /** @use HasFactory<ActivityFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'contract_id',
        'service_id',
        'title',
        'description',
        'activity_date',
        'duration_minutes',
        'reference_period',
        'time_entries',
        'is_in_progress',
        'images',
        'files',
        'external_links',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'activity_date' => 'date',
            'time_entries' => 'array',
            'is_in_progress' => 'boolean',
            'images' => 'array',
            'files' => 'array',
            'external_links' => 'array',
        ];
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function getDurationHoursAttribute(): float
    {
        return round($this->duration_minutes / 60, 2);
    }

    public function scopeInProgress(Builder $query): Builder
    {
        return $query->where('is_in_progress', true);
    }

    public function hasOpenTimeEntry(): bool
    {
        return collect($this->time_entries)
            ->contains(fn (mixed $entry): bool => is_array($entry) && filled($entry['started_at'] ?? null) && blank($entry['ended_at'] ?? null));
    }

    public static function calculateDurationMinutes(array $timeEntries): int
    {
        return (int) collect($timeEntries)
            ->sum(function (mixed $entry): int {
                if (! is_array($entry) || blank($entry['started_at'] ?? null)) {
                    return 0;
                }

                $startedAt = Carbon::parse($entry['started_at']);
                $endedAt = filled($entry['ended_at'] ?? null) ? Carbon::parse($entry['ended_at']) : now();

                return max($startedAt->diffInMinutes($endedAt), 0);
            });
    }

    public static function firstTrackedDate(array $timeEntries): ?string
    {
        foreach ($timeEntries as $entry) {
            if (filled($entry['started_at'] ?? null)) {
                return Carbon::parse($entry['started_at'])->toDateString();
            }
        }

        return null;
    }

    public static function sortTimeEntriesDescending(array $timeEntries): array
    {
        return collect($timeEntries)
            ->filter(fn (mixed $entry): bool => is_array($entry) && filled($entry['started_at'] ?? null))
            ->sortByDesc(fn (array $entry): int => Carbon::parse($entry['started_at'])->getTimestamp())
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    public function getExternalLinksListAttribute(): array
    {
        return collect($this->external_links)
            ->map(fn (mixed $link): ?string => is_array($link) ? ($link['url'] ?? null) : $link)
            ->filter()
            ->values()
            ->all();
    }

    public static function currentTask(): ?self
    {
        return static::query()
            ->inProgress()
            ->latest('updated_at')
            ->first();
    }
}
