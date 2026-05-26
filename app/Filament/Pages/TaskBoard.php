<?php

namespace App\Filament\Pages;

use App\ActivityKanbanStatus;
use App\ActivityPriority;
use App\Filament\Resources\Activities\ActivityResource;
use App\Models\Activity;
use App\Models\Contract;
use App\Models\Proposal;
use App\Models\Service;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class TaskBoard extends Page
{
    protected static ?string $title = 'Tarefas';

    protected static ?string $navigationLabel = 'Tarefas';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedViewColumns;

    protected static string|\UnitEnum|null $navigationGroup = 'Operacao';

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'tasks';

    protected string $view = 'filament.pages.task-board';

    protected Width|string|null $maxContentWidth = Width::Full;

    public bool $taskModalOpen = false;

    public bool $conversionModalOpen = false;

    public bool $conversionStartTimer = true;

    public ?int $editingTaskId = null;

    public ?int $conversionTaskId = null;

    /**
     * @var array<string, mixed>
     */
    public array $taskForm = [];

    public function mount(): void
    {
        $this->resetTaskForm();
    }

    public function getHeading(): string|Htmlable|null
    {
        return null;
    }

    /**
     * @return array<string, array{status: ActivityKanbanStatus, label: string, records: EloquentCollection<int, Activity>}>
     */
    public function columns(): array
    {
        $records = Activity::query()
            ->with(['contract.client', 'proposal.client', 'service'])
            ->orderBy('kanban_position')
            ->orderBy('id')
            ->get()
            ->groupBy(fn (Activity $activity): string => ($activity->kanban_status ?? ActivityKanbanStatus::Todo)->value);

        return collect(ActivityKanbanStatus::cases())
            ->mapWithKeys(fn (ActivityKanbanStatus $status): array => [
                $status->value => [
                    'status' => $status,
                    'label' => $status->label(),
                    'records' => $this->recordsForStatus($records->get($status->value, new EloquentCollection), $status),
                ],
            ])
            ->all();
    }

    /**
     * @param  EloquentCollection<int, Activity>  $records
     * @return EloquentCollection<int, Activity>
     */
    protected function recordsForStatus(EloquentCollection $records, ActivityKanbanStatus $status): EloquentCollection
    {
        if ($status !== ActivityKanbanStatus::Done) {
            return $records;
        }

        return $records
            ->sortByDesc(fn (Activity $activity): int => ($activity->completed_at ?? $activity->updated_at ?? $activity->created_at)?->getTimestamp() ?? 0)
            ->take(50)
            ->values();
    }

    /**
     * @return array<string, string>
     */
    public function priorityOptions(): array
    {
        return collect(ActivityPriority::cases())
            ->mapWithKeys(fn (ActivityPriority $priority): array => [$priority->value => $priority->label()])
            ->all();
    }

    /**
     * @return array<string, string>
     */
    public function statusOptions(): array
    {
        return collect(ActivityKanbanStatus::cases())
            ->mapWithKeys(fn (ActivityKanbanStatus $status): array => [$status->value => $status->label()])
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public function contractOptions(): array
    {
        return Contract::query()
            ->with('client')
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (Contract $contract): array => [
                $contract->getKey() => $contract->client?->name
                    ? "{$contract->name} - {$contract->client->name}"
                    : $contract->name,
            ])
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public function proposalOptions(): array
    {
        return Proposal::query()
            ->with('client')
            ->orderBy('title')
            ->get()
            ->mapWithKeys(fn (Proposal $proposal): array => [
                $proposal->getKey() => $proposal->client?->name
                    ? "{$proposal->title} - {$proposal->client->name}"
                    : $proposal->title,
            ])
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public function serviceOptions(): array
    {
        return Service::query()->orderBy('name')->pluck('name', 'id')->all();
    }

    public function openCreateTask(): void
    {
        $this->editingTaskId = null;
        $this->resetTaskForm();
        $this->taskModalOpen = true;
    }

    public function editTask(int $recordId): void
    {
        $activity = Activity::query()->findOrFail($recordId);

        $this->editingTaskId = $activity->getKey();
        $this->fillTaskForm($activity);
        $this->taskModalOpen = true;
    }

    public function closeTaskModal(): void
    {
        $this->taskModalOpen = false;
        $this->editingTaskId = null;
        $this->resetTaskForm();
        $this->resetValidation();
    }

    public function saveTask(): void
    {
        $wasEditing = $this->editingTaskId !== null;
        $activity = $this->editingTaskId
            ? Activity::query()->findOrFail($this->editingTaskId)
            : new Activity([
                'kanban_status' => ActivityKanbanStatus::Todo,
                'kanban_position' => $this->nextPosition(ActivityKanbanStatus::Todo),
                'show_on_task_board' => true,
            ]);

        $this->persistTaskForm($activity);
        $this->closeTaskModal();

        Notification::make()
            ->success()
            ->title($wasEditing ? 'Tarefa atualizada' : 'Tarefa criada')
            ->send();
    }

    /**
     * @param  list<int|string>  $orderedIds
     */
    public function moveRecord(int $recordId, string $status, array $orderedIds = []): void
    {
        $targetStatus = ActivityKanbanStatus::tryFrom($status);

        if ($targetStatus === null) {
            return;
        }

        $activity = Activity::query()->findOrFail($recordId);

        if ($targetStatus === ActivityKanbanStatus::InProgress && $activity->kanban_status !== ActivityKanbanStatus::InProgress) {
            $this->openConversionModal($activity);

            return;
        }

        if ($targetStatus === ActivityKanbanStatus::Done) {
            $activity->completeTask();
            $this->reorderStatus($targetStatus, $orderedIds);
            $this->dispatch('current-task-navigation-refresh');

            Notification::make()
                ->success()
                ->title('Tarefa concluida')
                ->send();

            return;
        }

        $activity->forceFill([
            'kanban_status' => $targetStatus,
            'show_on_task_board' => true,
            'completed_at' => null,
        ])->save();

        $this->reorderStatus($targetStatus, $orderedIds);
        $this->dispatch('current-task-navigation-refresh');
    }

    public function confirmConversion(): void
    {
        if ($this->conversionTaskId === null) {
            return;
        }

        $activity = Activity::query()->findOrFail($this->conversionTaskId);
        $this->persistTaskForm($activity);

        $activity->forceFill([
            'kanban_status' => ActivityKanbanStatus::InProgress,
            'kanban_position' => $this->nextPosition(ActivityKanbanStatus::InProgress),
            'show_on_task_board' => true,
            'completed_at' => null,
        ])->save();

        if ($this->conversionStartTimer) {
            $activity->refresh()->startTimer();
        }

        $this->conversionModalOpen = false;
        $this->conversionTaskId = null;
        $this->resetTaskForm();
        $this->dispatch('current-task-navigation-refresh');

        Notification::make()
            ->success()
            ->title($this->conversionStartTimer ? 'Atividade iniciada' : 'Tarefa movida para andamento')
            ->send();
    }

    public function cancelConversion(): void
    {
        $this->conversionModalOpen = false;
        $this->conversionTaskId = null;
        $this->resetTaskForm();
        $this->resetValidation();
    }

    public function fullEditUrl(int $recordId): string
    {
        return ActivityResource::getUrl('edit', ['record' => $recordId]);
    }

    public function elapsedTime(Activity $activity): string
    {
        return Activity::formatElapsedSeconds(Activity::openTimeEntryElapsedSeconds($activity->time_entries ?? []));
    }

    protected function openConversionModal(Activity $activity): void
    {
        $this->conversionTaskId = $activity->getKey();
        $this->conversionStartTimer = true;
        $this->fillTaskForm($activity);
        $this->conversionModalOpen = true;
    }

    protected function persistTaskForm(Activity $activity): void
    {
        $validated = $this->validate([
            'taskForm.title' => ['required', 'string', 'max:255'],
            'taskForm.kanban_status' => ['required', 'string'],
            'taskForm.priority' => ['required', 'string'],
            'taskForm.contract_id' => ['nullable', 'integer', 'exists:contracts,id'],
            'taskForm.proposal_id' => ['nullable', 'integer', 'exists:proposals,id'],
            'taskForm.service_id' => ['nullable', 'integer', 'exists:services,id'],
            'taskForm.activity_date' => ['nullable', 'date'],
            'taskForm.reference_period' => ['nullable', 'string', 'max:20'],
            'taskForm.description' => ['nullable', 'string'],
            'taskForm.external_links_text' => ['nullable', 'string'],
            'taskForm.start_timer' => ['boolean'],
        ])['taskForm'];

        $contractId = filled($validated['contract_id'] ?? null) ? (int) $validated['contract_id'] : null;
        $proposalId = filled($validated['proposal_id'] ?? null) ? (int) $validated['proposal_id'] : null;
        $status = ActivityKanbanStatus::tryFrom((string) ($validated['kanban_status'] ?? '')) ?? ActivityKanbanStatus::Todo;
        $statusChanged = (! $activity->exists) || (($activity->kanban_status ?? null) !== $status);

        if ($contractId !== null && $proposalId !== null) {
            throw ValidationException::withMessages([
                'taskForm.contract_id' => 'Escolha contrato ou proposta, nao ambos.',
            ]);
        }

        $activity->forceFill([
            'contract_id' => $contractId,
            'proposal_id' => $proposalId,
            'service_id' => filled($validated['service_id'] ?? null) ? (int) $validated['service_id'] : null,
            'title' => trim((string) $validated['title']),
            'priority' => ActivityPriority::tryFrom((string) ($validated['priority'] ?? '')) ?? ActivityPriority::Normal,
            'description' => filled($validated['description'] ?? null) ? (string) $validated['description'] : null,
            'activity_date' => filled($validated['activity_date'] ?? null)
                ? Carbon::parse((string) $validated['activity_date'])->toDateString()
                : now()->toDateString(),
            'reference_period' => filled($validated['reference_period'] ?? null) ? (string) $validated['reference_period'] : null,
            'external_links' => $this->parseExternalLinks((string) ($validated['external_links_text'] ?? '')),
            'kanban_status' => $status,
            'kanban_position' => $statusChanged ? $this->nextPosition($status) : $activity->kanban_position,
            'completed_at' => $status === ActivityKanbanStatus::Done ? ($activity->completed_at ?? now()) : null,
            'show_on_task_board' => true,
        ])->save();

        if ($status === ActivityKanbanStatus::Done) {
            $activity->completeTask();

            return;
        }

        if (($validated['start_timer'] ?? false) === true) {
            $activity->refresh()->startTimer();

            return;
        }

        if ($status !== ActivityKanbanStatus::InProgress && $activity->is_in_progress) {
            $activity->completeTask();
            $activity->forceFill([
                'kanban_status' => $status,
                'completed_at' => null,
            ])->save();
        }
    }

    /**
     * @return list<array{title: ?string, url: string}>
     */
    protected function parseExternalLinks(string $value): array
    {
        return collect(preg_split('/\R/', $value) ?: [])
            ->map(fn (string $line): string => trim($line))
            ->filter()
            ->map(fn (string $url): array => ['title' => null, 'url' => $url])
            ->values()
            ->all();
    }

    /**
     * @param  list<int|string>  $orderedIds
     */
    protected function reorderStatus(ActivityKanbanStatus $status, array $orderedIds): void
    {
        collect($orderedIds)
            ->map(fn (int|string $id): int => (int) $id)
            ->filter()
            ->values()
            ->each(function (int $id, int $index) use ($status): void {
                Activity::query()
                    ->whereKey($id)
                    ->where('kanban_status', $status->value)
                    ->update(['kanban_position' => $index + 1]);
            });
    }

    protected function nextPosition(ActivityKanbanStatus $status): int
    {
        return ((int) Activity::query()
            ->where('kanban_status', $status->value)
            ->max('kanban_position')) + 1;
    }

    protected function fillTaskForm(Activity $activity): void
    {
        $this->taskForm = [
            'title' => $activity->title,
            'kanban_status' => ($activity->kanban_status ?? ActivityKanbanStatus::Todo)->value,
            'priority' => ($activity->priority ?? ActivityPriority::Normal)->value,
            'contract_id' => $activity->contract_id,
            'proposal_id' => $activity->proposal_id,
            'service_id' => $activity->service_id,
            'activity_date' => $activity->activity_date?->toDateString() ?? now()->toDateString(),
            'reference_period' => $activity->reference_period,
            'description' => $activity->description,
            'external_links_text' => collect($activity->external_links ?? [])
                ->map(fn (mixed $link): ?string => is_array($link) ? ($link['url'] ?? null) : (is_string($link) ? $link : null))
                ->filter()
                ->implode(PHP_EOL),
            'start_timer' => false,
        ];
    }

    protected function resetTaskForm(): void
    {
        $this->taskForm = [
            'title' => '',
            'kanban_status' => ActivityKanbanStatus::Todo->value,
            'priority' => ActivityPriority::Normal->value,
            'contract_id' => null,
            'proposal_id' => null,
            'service_id' => null,
            'activity_date' => now()->toDateString(),
            'reference_period' => now()->format('Y-m'),
            'description' => '',
            'external_links_text' => '',
            'start_timer' => false,
        ];
    }
}
