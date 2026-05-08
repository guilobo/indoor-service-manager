<?php

namespace App\Models;

use App\Support\Uploads\LivewireUploadStore;
use Carbon\Carbon;
use Database\Factories\ActivityFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

class Activity extends Model
{
    /** @use HasFactory<ActivityFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::saving(function (self $activity): void {
            if ($activity->contract_id === null && $activity->proposal_id === null) {
                throw new InvalidArgumentException('A atividade deve estar vinculada a um contrato ou proposta.');
            }

            if ($activity->contract_id !== null && $activity->proposal_id !== null) {
                throw new InvalidArgumentException('A atividade deve estar vinculada a apenas um contrato ou proposta.');
            }
        });
    }

    /**
     * @var list<string>
     */
    protected $fillable = [
        'contract_id',
        'proposal_id',
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

    public function proposal(): BelongsTo
    {
        return $this->belongsTo(Proposal::class);
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

    public function scopeForClient(Builder $query, int|string|null $clientId): Builder
    {
        return $query->when($clientId, fn (Builder $builder): Builder => $builder
            ->where(function (Builder $clientQuery) use ($clientId): void {
                $clientQuery
                    ->whereHas('contract', fn (Builder $contractQuery): Builder => $contractQuery->where('client_id', $clientId))
                    ->orWhereHas('proposal', fn (Builder $proposalQuery): Builder => $proposalQuery->where('client_id', $clientId));
            }));
    }

    public function getClientNameAttribute(): string
    {
        return $this->contract?->client?->name
            ?? $this->proposal?->client?->name
            ?? '-';
    }

    public function getSourceLabelAttribute(): string
    {
        if ($this->contract_id !== null) {
            return 'Contrato';
        }

        if ($this->proposal_id !== null) {
            return 'Proposta';
        }

        return '-';
    }

    public function getSourceNameAttribute(): string
    {
        return $this->contract?->name
            ?? $this->proposal?->title
            ?? '-';
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
            ->map(function (mixed $link): ?string {
                if (is_array($link)) {
                    $url = $link['url'] ?? null;

                    if (blank($url)) {
                        return null;
                    }

                    $title = $link['title'] ?? null;

                    return filled($title) ? "{$title}: {$url}" : $url;
                }

                return is_string($link) ? $link : null;
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @param  array<int, mixed>|null  $links
     * @return list<array{title: ?string, url: string}>
     */
    public static function prepareExternalLinksForStorage(?array $links): array
    {
        return collect($links ?? [])
            ->map(function (mixed $link): ?array {
                if (is_string($link) && filled($link)) {
                    return [
                        'title' => null,
                        'url' => $link,
                    ];
                }

                if (! is_array($link) || blank($link['url'] ?? null)) {
                    return null;
                }

                return [
                    'title' => filled($link['title'] ?? null) ? (string) $link['title'] : null,
                    'url' => (string) $link['url'],
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return list<array{title: string, path: string, url: string}>
     */
    public function getImagesListAttribute(): array
    {
        return self::normalizeMediaItems($this->images);
    }

    /**
     * @return list<array{title: string, path: string, url: string}>
     */
    public function getFilesListAttribute(): array
    {
        return self::normalizeMediaItems($this->files);
    }

    /**
     * @param  array<int, mixed>|null  $items
     * @return list<array{title: string, path: string, url: string}>
     */
    public static function normalizeMediaItems(?array $items, ?string $temporaryUploadDirectory = null, bool $failWhenTemporaryUploadMissing = false): array
    {
        return collect($items ?? [])
            ->map(function (mixed $item) use ($failWhenTemporaryUploadMissing, $temporaryUploadDirectory): ?array {
                if (is_string($item) && filled($item)) {
                    if (LivewireUploadStore::isSerializedTemporaryUpload($item)) {
                        $item = $temporaryUploadDirectory === null
                            ? null
                            : LivewireUploadStore::storePublicly($item, $temporaryUploadDirectory, $failWhenTemporaryUploadMissing);

                        if ($item === null) {
                            return null;
                        }
                    }

                    return [
                        'title' => pathinfo($item, PATHINFO_FILENAME),
                        'path' => $item,
                        'url' => Storage::disk('public')->url($item),
                    ];
                }

                if (! is_array($item) || blank($item['path'] ?? null)) {
                    return null;
                }

                $path = $item['path'];

                if (is_array($path)) {
                    $path = collect($path)
                        ->filter(fn (mixed $value): bool => is_string($value) && filled($value))
                        ->first();
                }

                if (! is_string($path) || blank($path)) {
                    return null;
                }

                if (LivewireUploadStore::isSerializedTemporaryUpload($path)) {
                    $path = $temporaryUploadDirectory === null
                        ? null
                        : LivewireUploadStore::storePublicly($path, $temporaryUploadDirectory, $failWhenTemporaryUploadMissing);

                    if ($path === null) {
                        return null;
                    }
                }

                $title = filled($item['title'] ?? null)
                    ? (string) $item['title']
                    : pathinfo($path, PATHINFO_FILENAME);

                return [
                    'title' => $title,
                    'path' => $path,
                    'url' => Storage::disk('public')->url($path),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @param  array<int, mixed>|null  $items
     * @return list<array{title: string, path: string}>
     */
    public static function prepareMediaItemsForStorage(?array $items, ?string $temporaryUploadDirectory = null, bool $failWhenTemporaryUploadMissing = true): array
    {
        return collect(self::normalizeMediaItems($items, $temporaryUploadDirectory, $failWhenTemporaryUploadMissing))
            ->map(fn (array $item): array => [
                'title' => Str::of($item['title'])->trim()->value(),
                'path' => $item['path'],
            ])
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
