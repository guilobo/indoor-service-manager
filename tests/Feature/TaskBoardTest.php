<?php

use App\ActivityKanbanStatus;
use App\ActivityPriority;
use App\Filament\Pages\TaskBoard;
use App\Filament\Widgets\ReportsActivitiesTable;
use App\Models\Activity;
use App\Models\Client;
use App\Models\Contract;
use App\Models\Domain;
use App\Models\Proposal;
use App\Models\User;
use App\UserRole;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('renders the task board page for admins', function (): void {
    $user = User::factory()->create([
        'role' => UserRole::Admin,
    ]);

    $this->actingAs($user)
        ->get('/admin/tasks')
        ->assertSuccessful()
        ->assertSee('Nova tarefa');
});

it('creates a task with only a title on the task board', function (): void {
    $user = User::factory()->create([
        'role' => UserRole::Admin,
    ]);

    Livewire::actingAs($user)
        ->test(TaskBoard::class)
        ->set('taskForm.title', 'Retornar para cliente')
        ->call('saveTask')
        ->assertHasNoErrors();

    $activity = Activity::query()->sole();

    expect($activity->title)->toBe('Retornar para cliente')
        ->and($activity->contract_id)->toBeNull()
        ->and($activity->proposal_id)->toBeNull()
        ->and($activity->priority)->toBe(ActivityPriority::Normal)
        ->and($activity->kanban_status)->toBe(ActivityKanbanStatus::Todo)
        ->and($activity->show_on_task_board)->toBeTrue()
        ->and($activity->kanban_position)->toBe(1);
});

it('creates a task with an existing domain on the task board', function (): void {
    $user = User::factory()->create([
        'role' => UserRole::Admin,
    ]);

    $client = Client::factory()->create();
    $contract = Contract::factory()->create([
        'client_id' => $client->getKey(),
    ]);
    $domain = Domain::factory()->create([
        'client_id' => $client->getKey(),
        'contract_id' => $contract->getKey(),
    ]);

    Livewire::actingAs($user)
        ->test(TaskBoard::class)
        ->set('taskForm.title', 'Ajustar DNS')
        ->set('taskForm.contract_id', $contract->getKey())
        ->set('taskForm.domain_id', $domain->getKey())
        ->call('saveTask')
        ->assertHasNoErrors();

    $activity = Activity::query()->sole();

    expect($activity->domain_id)->toBe($domain->getKey());
});

it('keeps todo tasks ordered by insertion position', function (): void {
    Activity::factory()->create([
        'title' => 'Primeira',
        'contract_id' => null,
        'kanban_status' => ActivityKanbanStatus::Todo,
        'kanban_position' => 1,
        'show_on_task_board' => true,
    ]);

    Activity::factory()->create([
        'title' => 'Segunda',
        'contract_id' => null,
        'kanban_status' => ActivityKanbanStatus::Todo,
        'kanban_position' => 2,
        'show_on_task_board' => true,
    ]);

    $board = app(TaskBoard::class);
    $titles = $board->columns()[ActivityKanbanStatus::Todo->value]['records']
        ->pluck('title')
        ->all();

    expect($titles)->toBe(['Primeira', 'Segunda']);
});

it('opens an existing task in the edit modal and saves quick changes', function (): void {
    $user = User::factory()->create([
        'role' => UserRole::Admin,
    ]);

    $task = Activity::factory()->create([
        'title' => 'Texto antigo',
        'contract_id' => null,
        'kanban_status' => ActivityKanbanStatus::Todo,
        'priority' => ActivityPriority::Normal,
        'show_on_task_board' => true,
    ]);

    Livewire::actingAs($user)
        ->test(TaskBoard::class)
        ->call('editTask', $task->getKey())
        ->assertSet('taskModalOpen', true)
        ->assertSet('editingTaskId', $task->getKey())
        ->assertSet('taskForm.title', 'Texto antigo')
        ->set('taskForm.title', 'Texto atualizado')
        ->set('taskForm.priority', ActivityPriority::Urgent->value)
        ->set('taskForm.kanban_status', ActivityKanbanStatus::InProgress->value)
        ->call('saveTask')
        ->assertHasNoErrors();

    $task->refresh();

    expect($task->title)->toBe('Texto atualizado')
        ->and($task->priority)->toBe(ActivityPriority::Urgent)
        ->and($task->kanban_status)->toBe(ActivityKanbanStatus::InProgress)
        ->and($task->show_on_task_board)->toBeTrue();
});

it('creates a quick domain from a task contract and selects it automatically', function (): void {
    $user = User::factory()->create([
        'role' => UserRole::Admin,
    ]);

    $client = Client::factory()->create();
    $contract = Contract::factory()->create([
        'client_id' => $client->getKey(),
    ]);

    Livewire::actingAs($user)
        ->test(TaskBoard::class)
        ->set('taskForm.title', 'Publicar site')
        ->set('taskForm.contract_id', $contract->getKey())
        ->call('openQuickDomainModal')
        ->set('quickDomainForm.domain_name', 'site-cliente.com.br')
        ->call('saveQuickDomain')
        ->assertHasNoErrors()
        ->assertSet('taskForm.domain_id', Domain::query()->sole()->getKey());

    $domain = Domain::query()->sole();

    expect($domain->client_id)->toBe($client->getKey())
        ->and($domain->contract_id)->toBe($contract->getKey());
});

it('creates a quick domain from a task proposal and keeps the contract empty', function (): void {
    $user = User::factory()->create([
        'role' => UserRole::Admin,
    ]);

    $client = Client::factory()->create();
    $proposal = Proposal::factory()->create([
        'client_id' => $client->getKey(),
    ]);

    Livewire::actingAs($user)
        ->test(TaskBoard::class)
        ->set('taskForm.title', 'Preparar proposta')
        ->set('taskForm.proposal_id', $proposal->getKey())
        ->call('openQuickDomainModal')
        ->set('quickDomainForm.domain_name', 'proposta-cliente.com.br')
        ->call('saveQuickDomain')
        ->assertHasNoErrors();

    $domain = Domain::query()->sole();

    expect($domain->client_id)->toBe($client->getKey())
        ->and($domain->contract_id)->toBeNull();
});

it('keeps quick domain creation unavailable without contract or proposal', function (): void {
    Livewire::test(TaskBoard::class)
        ->assertSet('quickDomainModalOpen', false)
        ->call('openQuickDomainModal')
        ->assertSet('quickDomainModalOpen', false);
});

it('clears the selected domain when the task client changes', function (): void {
    $user = User::factory()->create([
        'role' => UserRole::Admin,
    ]);

    $firstClient = Client::factory()->create();
    $secondClient = Client::factory()->create();
    $firstContract = Contract::factory()->create([
        'client_id' => $firstClient->getKey(),
    ]);
    $secondContract = Contract::factory()->create([
        'client_id' => $secondClient->getKey(),
    ]);
    $domain = Domain::factory()->create([
        'client_id' => $firstClient->getKey(),
        'contract_id' => $firstContract->getKey(),
    ]);

    Livewire::actingAs($user)
        ->test(TaskBoard::class)
        ->set('taskForm.contract_id', $firstContract->getKey())
        ->set('taskForm.domain_id', $domain->getKey())
        ->set('taskForm.contract_id', $secondContract->getKey())
        ->assertSet('taskForm.domain_id', null);
});

it('renders rich editor json descriptions as plain text on the task board', function (): void {
    $description = json_encode([
        'type' => 'doc',
        'content' => [
            [
                'type' => 'paragraph',
                'content' => [
                    ['type' => 'text', 'text' => 'Primeira linha'],
                ],
            ],
            [
                'type' => 'paragraph',
                'content' => [
                    ['type' => 'text', 'text' => 'Segunda linha'],
                ],
            ],
        ],
    ]);

    $activity = Activity::factory()->create([
        'title' => 'Com JSON',
        'contract_id' => null,
        'description' => $description,
        'kanban_status' => ActivityKanbanStatus::Todo,
        'show_on_task_board' => true,
    ]);

    $board = app(TaskBoard::class);
    $record = $board->columns()[ActivityKanbanStatus::Todo->value]['records']->firstWhere('id', $activity->getKey());

    expect($record->plain_description)->toBe("Primeira linha\nSegunda linha");

    Livewire::test(TaskBoard::class)
        ->call('editTask', $activity->getKey())
        ->assertSet('taskForm.description', "Primeira linha\nSegunda linha");
});

it('opens the conversion modal when a todo task is moved to in progress and starts the timer on confirmation', function (): void {
    $user = User::factory()->create([
        'role' => UserRole::Admin,
    ]);

    $task = Activity::factory()->create([
        'title' => 'Investigar backup',
        'contract_id' => null,
        'time_entries' => [],
        'duration_minutes' => 0,
        'is_in_progress' => false,
        'kanban_status' => ActivityKanbanStatus::Todo,
        'show_on_task_board' => true,
    ]);

    Livewire::actingAs($user)
        ->test(TaskBoard::class)
        ->call('moveRecord', $task->getKey(), ActivityKanbanStatus::InProgress->value, [])
        ->assertSet('conversionModalOpen', true)
        ->assertSet('conversionTaskId', $task->getKey())
        ->call('confirmConversion')
        ->assertHasNoErrors();

    $task->refresh();

    expect($task->kanban_status)->toBe(ActivityKanbanStatus::InProgress)
        ->and($task->is_in_progress)->toBeTrue()
        ->and($task->time_entries)->toHaveCount(1)
        ->and($task->time_entries[0]['ended_at'])->toBeNull();
});

it('closes an open timer when a task is moved to done', function (): void {
    $user = User::factory()->create([
        'role' => UserRole::Admin,
    ]);

    $task = Activity::factory()->create([
        'contract_id' => null,
        'kanban_status' => ActivityKanbanStatus::InProgress,
        'show_on_task_board' => true,
        'is_in_progress' => true,
        'duration_minutes' => 0,
        'time_entries' => [
            [
                'started_at' => now()->subMinutes(42)->seconds(0)->toDateTimeString(),
                'ended_at' => null,
                'notes' => 'Executando',
            ],
        ],
    ]);

    Livewire::actingAs($user)
        ->test(TaskBoard::class)
        ->call('moveRecord', $task->getKey(), ActivityKanbanStatus::Done->value, [$task->getKey()])
        ->assertHasNoErrors();

    $task->refresh();

    expect($task->kanban_status)->toBe(ActivityKanbanStatus::Done)
        ->and($task->is_in_progress)->toBeFalse()
        ->and($task->completed_at)->not->toBeNull()
        ->and($task->time_entries[0]['ended_at'])->not->toBeNull()
        ->and($task->duration_minutes)->toBeGreaterThanOrEqual(41);
});

it('shows existing activities on the task board even when they were not explicitly pinned', function (): void {
    $activity = Activity::factory()->create([
        'title' => 'Atividade antiga',
        'is_in_progress' => true,
        'kanban_status' => ActivityKanbanStatus::InProgress,
        'show_on_task_board' => false,
        'time_entries' => [
            [
                'started_at' => now()->subMinutes(10)->toDateTimeString(),
                'ended_at' => null,
            ],
        ],
    ]);

    $board = app(TaskBoard::class);
    $ids = $board->columns()[ActivityKanbanStatus::InProgress->value]['records']
        ->pluck('id')
        ->all();

    expect($ids)->toContain($activity->getKey());
});

it('limits the done column to fifty most recent activities', function (): void {
    $oldestDone = Activity::factory()->create([
        'contract_id' => null,
        'service_id' => null,
        'title' => 'Concluida antiga',
        'kanban_status' => ActivityKanbanStatus::Done,
        'show_on_task_board' => false,
        'completed_at' => now()->subDays(90),
    ]);

    Activity::factory()
        ->count(50)
        ->sequence(fn (Sequence $sequence): array => [
            'contract_id' => null,
            'service_id' => null,
            'title' => "Concluida recente {$sequence->index}",
            'kanban_status' => ActivityKanbanStatus::Done,
            'show_on_task_board' => false,
            'completed_at' => now()->subDays($sequence->index),
        ])
        ->create();

    $doneRecords = app(TaskBoard::class)->columns()[ActivityKanbanStatus::Done->value]['records'];

    expect($doneRecords)->toHaveCount(50)
        ->and($doneRecords->pluck('id')->all())->not->toContain($oldestDone->getKey());
});

it('keeps unlinked tasks out of client activity reports', function (): void {
    Activity::factory()->create([
        'contract_id' => null,
        'proposal_id' => null,
        'title' => 'Lembrete solto',
        'show_on_task_board' => true,
        'duration_minutes' => 30,
    ]);

    $widget = new class extends ReportsActivitiesTable
    {
        public function configuredTable(): Table
        {
            return $this->table(Table::make($this));
        }
    };

    expect($widget->configuredTable()->getQuery()->count())->toBe(0);
});
