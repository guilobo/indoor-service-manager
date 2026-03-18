<?php

use App\ContractStatus;
use App\DomainStatus;
use App\Filament\Resources\Activities\ActivityResource;
use App\Models\Activity;
use App\Models\Client;
use App\Models\Contract;
use App\Models\Domain;
use App\Models\User;
use App\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('calculates contract usage summary and estimated values correctly', function () {
    $client = Client::factory()->create();
    $contract = Contract::factory()->create([
        'client_id' => $client->id,
        'monthly_hours' => 10,
        'hourly_rate' => 100,
        'domain_rate' => 25,
        'status' => ContractStatus::Active,
    ]);

    Domain::factory()->count(2)->create([
        'client_id' => $client->id,
        'contract_id' => $contract->id,
        'status' => DomainStatus::Active,
    ]);

    Activity::factory()->create([
        'contract_id' => $contract->id,
        'duration_minutes' => 300,
        'activity_date' => now()->toDateString(),
    ]);

    Activity::factory()->create([
        'contract_id' => $contract->id,
        'duration_minutes' => 240,
        'activity_date' => now()->toDateString(),
    ]);

    $summary = $contract->usageSummary(now()->startOfMonth(), now()->endOfMonth());

    expect($contract->total_domains)->toBe(2)
        ->and($contract->domain_cost)->toBe(50.0)
        ->and($contract->hours_cost)->toBe(1000.0)
        ->and($contract->estimated_monthly_value)->toBe(1050.0)
        ->and($summary['total_used_minutes'])->toBe(540)
        ->and($summary['total_used_hours'])->toBe(9.0)
        ->and($summary['remaining_hours'])->toBe(1.0)
        ->and($summary['status'])->toBe('warning');
});

it('encrypts sensitive domain fields at rest', function () {
    $domain = Domain::factory()->create([
        'credentials' => ['usuario' => 'ftp-user', 'senha' => 'segredo'],
        'ftp_password' => 'super-secreta',
        'email_accounts' => [
            ['email' => 'suporte@indoor.test', 'password' => 'senha-email'],
        ],
    ]);

    $rawValues = Domain::query()
        ->withoutGlobalScopes()
        ->whereKey($domain->id)
        ->first()
        ?->getRawOriginal();

    expect($rawValues['credentials'])->not->toContain('ftp-user')
        ->and($rawValues['ftp_password'])->not->toContain('super-secreta')
        ->and($rawValues['email_accounts'])->not->toContain('senha-email')
        ->and($domain->fresh()->credentials)->toBe(['usuario' => 'ftp-user', 'senha' => 'segredo'])
        ->and($domain->fresh()->ftp_password)->toBe('super-secreta')
        ->and($domain->fresh()->email_accounts)->toBe([
            ['email' => 'suporte@indoor.test', 'password' => 'senha-email'],
        ]);
});

it('reads legacy plain json email accounts without decryption errors', function () {
    $domain = Domain::factory()->create();

    DB::table('domains')
        ->where('id', $domain->id)
        ->update([
            'email_accounts' => json_encode([
                ['email' => 'legado@indoor.test', 'password' => '123456'],
            ], JSON_UNESCAPED_UNICODE),
        ]);

    expect($domain->fresh()->email_accounts)->toBe([
        ['email' => 'legado@indoor.test', 'password' => '123456'],
    ]);
});

it('allows admin users to access the reports page', function () {
    $user = User::factory()->create([
        'role' => UserRole::Admin,
    ]);

    $this->actingAs($user)
        ->get('/admin/reports')
        ->assertSuccessful()
        ->assertSee('Relatorios');
});

it('calculates activity duration from time entries', function () {
    $minutes = Activity::calculateDurationMinutes([
        [
            'started_at' => '2026-03-18 09:00:00',
            'ended_at' => '2026-03-18 10:30:00',
        ],
        [
            'started_at' => '2026-03-18 11:00:00',
            'ended_at' => '2026-03-18 11:45:00',
        ],
    ]);

    expect($minutes)->toBe(135)
        ->and(Activity::firstTrackedDate([
            ['started_at' => '2026-03-18 09:00:00', 'ended_at' => null],
        ]))->toBe('2026-03-18');
});

it('normalizes uploaded media items when the upload path arrives as an array', function () {
    expect(Activity::normalizeMediaItems([
        [
            'title' => 'Manual do cliente',
            'path' => ['activities/files/manual.pdf'],
        ],
    ]))->toBe([
        [
            'title' => 'Manual do cliente',
            'path' => 'activities/files/manual.pdf',
            'url' => url('/storage/activities/files/manual.pdf'),
        ],
    ]);
});

it('redirects the current task page to the running activity', function () {
    $user = User::factory()->create([
        'role' => UserRole::Admin,
    ]);

    $activity = Activity::factory()->create([
        'is_in_progress' => true,
        'time_entries' => [
            [
                'started_at' => now()->subMinutes(10)->format('Y-m-d H:i:s'),
                'ended_at' => null,
            ],
        ],
    ]);

    $this->actingAs($user)
        ->get('/admin/current-task')
        ->assertRedirect(ActivityResource::getUrl('edit', ['record' => $activity], panel: 'admin'));
});
