<?php

namespace App\Models;

use App\ContractStatus;
use Carbon\CarbonInterface;
use Database\Factories\ContractFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Contract extends Model
{
    /** @use HasFactory<ContractFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'client_id',
        'name',
        'description',
        'start_date',
        'end_date',
        'monthly_hours',
        'hourly_rate',
        'domain_rate',
        'status',
        'notes',
        'contract_file',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'monthly_hours' => 'decimal:2',
            'hourly_rate' => 'decimal:2',
            'domain_rate' => 'decimal:2',
            'status' => ContractStatus::class,
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function domains(): HasMany
    {
        return $this->hasMany(Domain::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class);
    }

    public function scopeWithinValidity(Builder $query): Builder
    {
        return $query->whereDate('start_date', '<=', now())
            ->where(function (Builder $validityQuery): void {
                $validityQuery
                    ->whereNull('end_date')
                    ->orWhereDate('end_date', '>=', now());
            });
    }

    public function getTotalDomainsAttribute(): int
    {
        return $this->domains_count ?? $this->domains()->count();
    }

    public function getDomainCostAttribute(): float
    {
        return $this->total_domains * (float) ($this->domain_rate ?? 0);
    }

    public function getHoursCostAttribute(): float
    {
        return (float) ($this->monthly_hours ?? 0) * (float) ($this->hourly_rate ?? 0);
    }

    public function getEstimatedMonthlyValueAttribute(): float
    {
        return $this->domain_cost + $this->hours_cost;
    }

    /**
     * @return array{total_used_minutes:int,total_used_hours:float,remaining_hours:float,exceeded_hours:float,usage_percentage:float,status:string}
     */
    public function usageSummary(?CarbonInterface $startDate = null, ?CarbonInterface $endDate = null): array
    {
        $activityQuery = $this->activities();

        if ($startDate !== null) {
            $activityQuery->whereDate('activity_date', '>=', $startDate);
        }

        if ($endDate !== null) {
            $activityQuery->whereDate('activity_date', '<=', $endDate);
        }

        $totalUsedMinutes = (int) $activityQuery->sum('duration_minutes');
        $totalUsedHours = round($totalUsedMinutes / 60, 2);
        $monthlyHours = (float) ($this->monthly_hours ?? 0);
        $remainingHours = round(max($monthlyHours - $totalUsedHours, 0), 2);
        $exceededHours = round(max($totalUsedHours - $monthlyHours, 0), 2);
        $usagePercentage = $monthlyHours > 0 ? round(($totalUsedHours / $monthlyHours) * 100, 2) : 0.0;

        $status = 'ok';

        if ($totalUsedHours > $monthlyHours) {
            $status = 'exceeded';
        } elseif ($usagePercentage >= 80) {
            $status = 'warning';
        }

        return [
            'total_used_minutes' => $totalUsedMinutes,
            'total_used_hours' => $totalUsedHours,
            'remaining_hours' => $remainingHours,
            'exceeded_hours' => $exceededHours,
            'usage_percentage' => $usagePercentage,
            'status' => $status,
        ];
    }
}
