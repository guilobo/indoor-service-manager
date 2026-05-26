<?php

namespace App\Models;

use App\ActivityKanbanStatus;
use App\ActivityPriority;
use App\Support\Uploads\LivewireUploadStore;
use Carbon\Carbon;
use Carbon\CarbonInterface;
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
            if ($activity->contract_id !== null && $activity->proposal_id !== null) {
                throw new InvalidArgumentException('A atividade deve estar vinculada a apenas um contrato ou proposta.');
            }
        });

        static::creating(function (self $activity): void {
            $activity->activity_date ??= now()->toDateString();
            $activity->kanban_status ??= ActivityKanbanStatus::Todo;
            $activity->priority ??= ActivityPriority::Normal;
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
        'kanban_status',
        'kanban_position',
        'priority',
        'completed_at',
        'show_on_task_board',
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
            'kanban_status' => ActivityKanbanStatus::class,
            'priority' => ActivityPriority::class,
            'completed_at' => 'datetime',
            'show_on_task_board' => 'boolean',
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

    public function getPlainDescriptionAttribute(): string
    {
        return self::plainTextFromDescription($this->description);
    }

    public static function plainTextFromDescription(?string $description): string
    {
        if (blank($description)) {
            return '';
        }

        $description = trim($description);
        $decodedDescription = json_decode($description, true);

        if (json_last_error() === JSON_ERROR_NONE && is_array($decodedDescription)) {
            return Str::of(self::plainTextFromRichTextNode($decodedDescription))
                ->replaceMatches('/[ \t]+/', ' ')
                ->replaceMatches('/\n{3,}/', "\n\n")
                ->trim()
                ->value();
        }

        return Str::of(strip_tags($description))
            ->replaceMatches('/\s+/', ' ')
            ->trim()
            ->value();
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

    public function scopeLinkedToClient(Builder $query): Builder
    {
        return $query->where(function (Builder $linkedQuery): void {
            $linkedQuery
                ->whereNotNull('contract_id')
                ->orWhereNotNull('proposal_id');
        });
    }

    public function scopeVisibleOnTaskBoard(Builder $query): Builder
    {
        return $query->where(function (Builder $boardQuery): void {
            $boardQuery
                ->where('show_on_task_board', true)
                ->orWhere('is_in_progress', true);
        });
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
        return self::openTimeEntry($this->time_entries ?? []) !== null;
    }

    public static function openTimeEntry(array $timeEntries): ?array
    {
        return collect($timeEntries)
            ->first(fn (mixed $entry): bool => is_array($entry) && filled($entry['started_at'] ?? null) && blank($entry['ended_at'] ?? null));
    }

    public static function openTimeEntryElapsedSeconds(array $timeEntries, ?CarbonInterface $now = null): int
    {
        $openEntry = self::openTimeEntry($timeEntries);

        if (! is_array($openEntry) || blank($openEntry['started_at'] ?? null)) {
            return 0;
        }

        return max(Carbon::parse($openEntry['started_at'])->diffInSeconds($now ?? now()), 0);
    }

    public static function formatElapsedSeconds(int $seconds): string
    {
        return gmdate('H:i:s', max($seconds, 0));
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
            ->map(fn (array $entry): array => [
                'started_at' => $entry['started_at'],
                'ended_at' => $entry['ended_at'] ?? null,
                'notes' => filled($entry['notes'] ?? null) ? (string) $entry['notes'] : null,
            ])
            ->values()
            ->all();
    }

    protected static function plainTextFromRichTextNode(mixed $node): string
    {
        if (! is_array($node)) {
            return '';
        }

        if (is_string($node['text'] ?? null)) {
            return $node['text'];
        }

        if (($node['type'] ?? null) === 'hardBreak') {
            return "\n";
        }

        $text = collect($node['content'] ?? [])
            ->map(fn (mixed $child): string => self::plainTextFromRichTextNode($child))
            ->implode('');

        if (in_array($node['type'] ?? null, ['paragraph', 'heading', 'blockquote', 'listItem'], true)) {
            return trim($text)."\n";
        }

        return $text;
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
                if ((is_string($item) && filled($item)) || LivewireUploadStore::isTemporaryUpload($item)) {
                    $path = self::storedMediaPath($item, $temporaryUploadDirectory, $failWhenTemporaryUploadMissing);

                    if ($path === null) {
                        return null;
                    }

                    return [
                        'title' => pathinfo($path, PATHINFO_FILENAME),
                        'path' => $path,
                        'url' => Storage::disk('public')->url($path),
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

                $path = self::storedMediaPath($path, $temporaryUploadDirectory, $failWhenTemporaryUploadMissing);

                if ($path === null) {
                    return null;
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

    protected static function storedMediaPath(mixed $path, ?string $temporaryUploadDirectory, bool $failWhenTemporaryUploadMissing): ?string
    {
        if (LivewireUploadStore::isTemporaryUpload($path)) {
            return $temporaryUploadDirectory === null
                ? null
                : LivewireUploadStore::storePublicly($path, $temporaryUploadDirectory, $failWhenTemporaryUploadMissing);
        }

        if (! is_string($path) || blank($path)) {
            return null;
        }

        return $path;
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

    public function startTimer(?CarbonInterface $now = null): void
    {
        $now ??= now();
        $timeEntries = self::sortTimeEntriesDescending($this->time_entries ?? []);

        if (self::openTimeEntry($timeEntries) === null) {
            array_unshift($timeEntries, [
                'started_at' => $now->copy()->seconds(0)->toDateTimeString(),
                'ended_at' => null,
                'notes' => null,
            ]);
        }

        $timeEntries = self::sortTimeEntriesDescending($timeEntries);

        $this->forceFill([
            'time_entries' => $timeEntries,
            'duration_minutes' => self::calculateDurationMinutes($timeEntries),
            'is_in_progress' => true,
            'kanban_status' => ActivityKanbanStatus::InProgress,
            'show_on_task_board' => true,
            'completed_at' => null,
        ])->save();
    }

    public function completeTask(?CarbonInterface $now = null): void
    {
        $now ??= now();
        $timeEntries = collect($this->time_entries ?? [])
            ->map(function (mixed $entry) use ($now): mixed {
                if (! is_array($entry) || blank($entry['started_at'] ?? null) || filled($entry['ended_at'] ?? null)) {
                    return $entry;
                }

                $entry['ended_at'] = $now->copy()->seconds(0)->toDateTimeString();

                return $entry;
            })
            ->all();

        $timeEntries = self::sortTimeEntriesDescending($timeEntries);

        $this->forceFill([
            'time_entries' => $timeEntries,
            'duration_minutes' => self::calculateDurationMinutes($timeEntries),
            'is_in_progress' => false,
            'kanban_status' => ActivityKanbanStatus::Done,
            'show_on_task_board' => true,
            'completed_at' => $now,
        ])->save();
    }
}
