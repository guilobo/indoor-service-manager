<?php

namespace App\Models;

use App\ProposalBillingType;
use App\ProposalStatus;
use App\Support\Uploads\LivewireUploadStore;
use Database\Factories\ProposalFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Proposal extends Model
{
    /** @use HasFactory<ProposalFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'client_id',
        'title',
        'description',
        'billing_type',
        'hours',
        'hourly_rate',
        'fixed_value',
        'status',
        'proposal_file',
        'attachments',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'billing_type' => ProposalBillingType::class,
            'hours' => 'decimal:2',
            'hourly_rate' => 'decimal:2',
            'fixed_value' => 'decimal:2',
            'status' => ProposalStatus::class,
            'attachments' => 'array',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class);
    }

    public function getEstimatedValueAttribute(): float
    {
        if ($this->billing_type === ProposalBillingType::Fixed) {
            return (float) ($this->fixed_value ?? 0);
        }

        return (float) ($this->hours ?? 0) * (float) ($this->hourly_rate ?? 0);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function normalizeBillingData(array $data): array
    {
        $billingType = $data['billing_type'] ?? ProposalBillingType::Hourly->value;

        if ($billingType instanceof ProposalBillingType) {
            $billingType = $billingType->value;
        }

        $data['billing_type'] = $billingType;

        if ($billingType === ProposalBillingType::Fixed->value) {
            $data['hours'] = null;
            $data['hourly_rate'] = null;

            return $data;
        }

        $data['fixed_value'] = null;

        return $data;
    }

    /**
     * @return list<array{title: string, path: string, url: string}>
     */
    public function getAttachmentsListAttribute(): array
    {
        return self::normalizeAttachmentItems($this->attachments);
    }

    /**
     * @param  array<int, mixed>|null  $items
     * @return list<array{title: string, path: string, url: string}>
     */
    public static function normalizeAttachmentItems(?array $items, ?string $temporaryUploadDirectory = null, bool $failWhenTemporaryUploadMissing = false): array
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
    public static function prepareAttachmentItemsForStorage(?array $items, ?string $temporaryUploadDirectory = null, bool $failWhenTemporaryUploadMissing = true): array
    {
        return collect(self::normalizeAttachmentItems($items, $temporaryUploadDirectory, $failWhenTemporaryUploadMissing))
            ->map(fn (array $item): array => [
                'title' => Str::of($item['title'])->trim()->value(),
                'path' => $item['path'],
            ])
            ->values()
            ->all();
    }
}
