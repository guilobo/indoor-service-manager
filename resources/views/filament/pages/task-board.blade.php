<x-filament-panels::page>
    <div
        class="task-board-shell -mx-4 -mt-3 space-y-6 px-4 sm:-mx-6 sm:px-6 lg:-mx-8 lg:px-8"
        x-data="{
            draggedId: null,
            dragStart(id) {
                this.draggedId = String(id)
            },
            dragOver(event) {
                if (! this.draggedId) {
                    return
                }

                const list = event.currentTarget
                const dragged = list.ownerDocument.querySelector(`[data-record-id='${this.draggedId}']`)
                const target = event.target.closest('[data-record-id]')

                if (! dragged || ! target || target.dataset.recordId === this.draggedId) {
                    return
                }

                const bounds = target.getBoundingClientRect()
                const afterTarget = event.clientY > bounds.top + (bounds.height / 2)

                list.insertBefore(dragged, afterTarget ? target.nextSibling : target)
            },
            drop(status, event) {
                if (! this.draggedId) {
                    return
                }

                const ids = Array.from(event.currentTarget.querySelectorAll('[data-record-id]'))
                    .map((element) => element.dataset.recordId)

                this.$wire.moveRecord(Number(this.draggedId), status, ids)
                this.draggedId = null
            },
        }"
    >
        <header class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-3xl font-black text-amber-400 sm:text-4xl">Tarefas</h2>
                <p class="mt-1 max-w-2xl text-sm text-gray-500 dark:text-gray-400">
                    Capture demandas, mova para execucao e conclua com o timer no mesmo fluxo.
                </p>
            </div>

            <button
                type="button"
                wire:click="openCreateTask"
                class="inline-flex h-12 items-center justify-center gap-2 rounded-lg bg-amber-500 px-5 text-sm font-bold text-white shadow-lg shadow-amber-950/20 transition hover:bg-amber-400 focus:outline-none focus:ring-2 focus:ring-amber-300"
            >
                <x-heroicon-o-plus class="h-5 w-5" />
                Nova tarefa
            </button>
        </header>

        <div class="max-w-full overflow-x-auto pb-3">
            <div class="grid min-h-[42rem] w-full min-w-[54rem] grid-cols-3 gap-4 lg:min-w-0">
                @foreach ($this->columns() as $column)
                    <section class="task-board-column flex min-h-[34rem] flex-col overflow-hidden rounded-lg bg-gray-100 ring-1 ring-gray-200 dark:bg-[#17171a] dark:ring-white/10" wire:key="task-column-{{ $column['status']->value }}">
                    <header class="px-5 pb-3 pt-5">
                        <h3 class="text-lg font-bold text-amber-500 dark:text-amber-300">
                            {{ $column['label'] }}
                            <span class="font-semibold text-gray-500 dark:text-gray-400">({{ $column['records']->count() }})</span>
                        </h3>
                    </header>

                    <div
                        class="flex flex-1 flex-col gap-3 px-4 pb-5"
                        data-status="{{ $column['status']->value }}"
                        x-on:dragover.prevent="dragOver($event)"
                        x-on:drop.prevent="drop(@js($column['status']->value), $event)"
                    >
                        @forelse ($column['records'] as $activity)
                            <article
                                draggable="true"
                                data-record-id="{{ $activity->getKey() }}"
                                x-on:dragstart="dragStart({{ $activity->getKey() }})"
                                x-on:keydown.enter.prevent="$wire.editTask({{ $activity->getKey() }})"
                                x-on:keydown.space.prevent="$wire.editTask({{ $activity->getKey() }})"
                                wire:click="editTask({{ $activity->getKey() }})"
                                wire:key="task-card-{{ $activity->getKey() }}"
                                role="button"
                                tabindex="0"
                                @php($priority = $activity->priority ?? \App\ActivityPriority::Normal)
                                @php($accent = match ($priority) {
                                    \App\ActivityPriority::Low => 'bg-cyan-300',
                                    \App\ActivityPriority::Normal => 'bg-amber-400',
                                    \App\ActivityPriority::High => 'bg-purple-400',
                                    \App\ActivityPriority::Urgent => 'bg-red-500',
                                })
                                class="group relative cursor-pointer overflow-hidden rounded-lg bg-white p-4 pl-6 shadow-sm ring-1 ring-gray-200 transition hover:-translate-y-0.5 hover:ring-amber-300 focus:outline-none focus:ring-2 focus:ring-amber-300 dark:bg-[#2d2d31] dark:ring-white/10 dark:hover:ring-amber-400/60 dark:focus:ring-amber-400/70"
                            >
                                <span class="absolute inset-y-0 left-0 w-2 {{ $accent }}"></span>

                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0 text-left">
                                        <span class="block text-base font-bold leading-6 text-gray-900 group-hover:text-amber-700 dark:text-gray-100 dark:group-hover:text-amber-300">
                                            {{ $activity->title }}
                                        </span>
                                    </div>

                                    <span class="shrink-0 rounded-full px-2.5 py-1 text-xs font-semibold ring-1 {{ $priority->colorClasses() }}">
                                        {{ $priority->label() }}
                                    </span>
                                </div>

                                <div class="mt-3 border-l-2 border-gray-200 pl-3 dark:border-white/15">
                                    <p class="line-clamp-2 text-sm leading-5 text-gray-600 dark:text-gray-300">
                                        @if (filled($activity->description))
                                            {{ $activity->plain_description }}
                                        @else
                                            {{ $activity->source_name !== '-' ? $activity->source_name : 'Sem cliente definido' }}
                                        @endif
                                    </p>
                                </div>

                                <div class="mt-4 flex items-center gap-3 text-xs text-gray-500 dark:text-gray-300">
                                    <span class="inline-flex items-center gap-1 rounded-md bg-gray-100 px-2.5 py-1.5 font-medium uppercase dark:bg-[#202024]">
                                        <x-heroicon-o-clock class="h-4 w-4" />
                                        {{ $activity->activity_date?->format('d/m') }}
                                    </span>

                                    @if ($activity->is_in_progress && $activity->hasOpenTimeEntry())
                                        <span class="inline-flex items-center gap-1 rounded-md bg-emerald-50 px-2.5 py-1.5 font-semibold text-emerald-700 ring-1 ring-emerald-600/20 dark:bg-emerald-400/10 dark:text-emerald-300 dark:ring-emerald-400/20" wire:poll.1s>
                                            <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                                            {{ $this->elapsedTime($activity) }}
                                        </span>
                                    @elseif ($activity->duration_minutes > 0)
                                        <span class="inline-flex items-center gap-1 rounded-md bg-gray-100 px-2.5 py-1.5 font-medium dark:bg-[#202024]">
                                            {{ intdiv($activity->duration_minutes, 60) }}h {{ $activity->duration_minutes % 60 }}m
                                        </span>
                                    @endif
                                </div>

                                <div class="mt-4 h-px bg-gray-200 dark:bg-white/10"></div>

                                <div class="mt-3 flex items-center justify-between gap-2">
                                    <div class="flex min-w-0 flex-wrap gap-2 text-xs text-gray-500 dark:text-gray-400">
                                        @if ($activity->service?->name)
                                            <span class="rounded-full bg-gray-100 px-2 py-1 dark:bg-[#232327]">{{ $activity->service->name }}</span>
                                        @endif
                                        @if ($activity->domain?->domain_name)
                                            <span class="rounded-full bg-sky-50 px-2 py-1 text-sky-700 dark:bg-sky-400/10 dark:text-sky-300">{{ $activity->domain->domain_name }}</span>
                                        @endif
                                        <span class="rounded-full bg-gray-100 px-2 py-1 dark:bg-[#232327]">{{ $activity->source_label }}</span>
                                    </div>

                                    <a
                                        href="{{ $this->fullEditUrl($activity->getKey()) }}"
                                        x-on:click.stop
                                        class="text-xs font-medium text-gray-500 transition hover:text-amber-700 dark:text-gray-400 dark:hover:text-amber-300"
                                    >
                                        Abrir
                                    </a>
                                </div>
                            </article>
                        @empty
                            <div class="flex min-h-40 flex-1 items-center justify-center rounded-lg border border-dashed border-gray-300/80 px-4 py-8 text-center text-sm text-gray-500 dark:border-white/10 dark:text-gray-500">
                                Solte uma tarefa aqui
                            </div>
                        @endforelse
                    </div>
                    </section>
                @endforeach
            </div>
        </div>

        @if ($taskModalOpen || $conversionModalOpen)
            @php($fullEditId = $editingTaskId ?? $conversionTaskId)

            <div class="fixed inset-0 z-50 flex items-center justify-center bg-gray-950/70 px-4 py-8">
                <section class="max-h-[92vh] w-full max-w-3xl overflow-hidden rounded-lg bg-white shadow-xl ring-1 ring-black/5 dark:bg-gray-900 dark:ring-white/10">
                    <div class="flex items-start justify-between gap-4 border-b border-gray-200 px-5 py-4 dark:border-white/10">
                        <div>
                            <h2 class="text-lg font-semibold text-gray-950 dark:text-white">
                                {{ $conversionModalOpen ? 'Iniciar atividade' : ($editingTaskId ? 'Editar tarefa' : 'Nova tarefa') }}
                            </h2>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                O titulo e o unico campo obrigatorio. Os demais ajudam quando a tarefa virar execucao.
                            </p>
                        </div>

                        <button
                            type="button"
                            wire:click="{{ $conversionModalOpen ? 'cancelConversion' : 'closeTaskModal' }}"
                            class="rounded-md p-2 text-gray-400 transition hover:bg-gray-100 hover:text-gray-700 dark:hover:bg-white/10 dark:hover:text-gray-200"
                        >
                            <x-heroicon-o-x-mark class="h-5 w-5" />
                        </button>
                    </div>

                    <div class="max-h-[70vh] overflow-y-auto px-5 py-5">
                        <div class="grid gap-4 md:grid-cols-2">
                            <label class="md:col-span-2">
                                <span class="text-sm font-medium text-gray-700 dark:text-gray-200">Titulo</span>
                                <input
                                    type="text"
                                    wire:model="taskForm.title"
                                    class="mt-1 block w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-950 shadow-sm outline-none transition focus:border-amber-500 focus:ring-2 focus:ring-amber-500/20 dark:border-white/10 dark:bg-gray-950 dark:text-white"
                                />
                                @error('taskForm.title')
                                    <span class="mt-1 block text-xs text-red-600 dark:text-red-300">{{ $message }}</span>
                                @enderror
                            </label>

                            <label>
                                <span class="text-sm font-medium text-gray-700 dark:text-gray-200">Prioridade</span>
                                <select wire:model="taskForm.priority" class="mt-1 block w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-950 shadow-sm outline-none transition focus:border-amber-500 focus:ring-2 focus:ring-amber-500/20 dark:border-white/10 dark:bg-gray-950 dark:text-white">
                                    @foreach ($this->priorityOptions() as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </label>

                            @if (! $conversionModalOpen)
                                <label>
                                    <span class="text-sm font-medium text-gray-700 dark:text-gray-200">Status</span>
                                    <select wire:model.live="taskForm.kanban_status" class="mt-1 block w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-950 shadow-sm outline-none transition focus:border-amber-500 focus:ring-2 focus:ring-amber-500/20 dark:border-white/10 dark:bg-gray-950 dark:text-white">
                                        @foreach ($this->statusOptions() as $value => $label)
                                            <option value="{{ $value }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </label>
                            @endif

                            <label>
                                <span class="text-sm font-medium text-gray-700 dark:text-gray-200">Data</span>
                                <input
                                    type="date"
                                    wire:model="taskForm.activity_date"
                                    class="mt-1 block w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-950 shadow-sm outline-none transition focus:border-amber-500 focus:ring-2 focus:ring-amber-500/20 dark:border-white/10 dark:bg-gray-950 dark:text-white"
                                />
                            </label>

                            <label>
                                <span class="text-sm font-medium text-gray-700 dark:text-gray-200">Contrato</span>
                                <select wire:model="taskForm.contract_id" class="mt-1 block w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-950 shadow-sm outline-none transition focus:border-amber-500 focus:ring-2 focus:ring-amber-500/20 dark:border-white/10 dark:bg-gray-950 dark:text-white">
                                    <option value="">Sem contrato</option>
                                    @foreach ($this->contractOptions() as $id => $label)
                                        <option value="{{ $id }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('taskForm.contract_id')
                                    <span class="mt-1 block text-xs text-red-600 dark:text-red-300">{{ $message }}</span>
                                @enderror
                            </label>

                            <label>
                                <span class="text-sm font-medium text-gray-700 dark:text-gray-200">Proposta</span>
                                <select wire:model="taskForm.proposal_id" class="mt-1 block w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-950 shadow-sm outline-none transition focus:border-amber-500 focus:ring-2 focus:ring-amber-500/20 dark:border-white/10 dark:bg-gray-950 dark:text-white">
                                    <option value="">Sem proposta</option>
                                    @foreach ($this->proposalOptions() as $id => $label)
                                        <option value="{{ $id }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </label>

                            <div class="md:col-span-2">
                                <div class="flex items-center justify-between gap-3">
                                    <span class="text-sm font-medium text-gray-700 dark:text-gray-200">Dominio</span>

                                    <button
                                        type="button"
                                        wire:click="openQuickDomainModal"
                                        @disabled(! $this->canQuickCreateDomain())
                                        class="inline-flex items-center gap-1 rounded-md px-2.5 py-1 text-xs font-semibold transition {{ $this->canQuickCreateDomain() ? 'bg-sky-50 text-sky-700 hover:bg-sky-100 dark:bg-sky-400/10 dark:text-sky-300 dark:hover:bg-sky-400/20' : 'bg-gray-100 text-gray-400 dark:bg-white/5 dark:text-gray-500' }}"
                                    >
                                        <x-heroicon-o-plus class="h-4 w-4" />
                                        Novo dominio
                                    </button>
                                </div>

                                <select wire:model="taskForm.domain_id" class="mt-1 block w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-950 shadow-sm outline-none transition focus:border-amber-500 focus:ring-2 focus:ring-amber-500/20 dark:border-white/10 dark:bg-gray-950 dark:text-white">
                                    <option value="">Sem dominio</option>
                                    @foreach ($this->domainOptions() as $id => $label)
                                        <option value="{{ $id }}">{{ $label }}</option>
                                    @endforeach
                                </select>

                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    @if ($this->canQuickCreateDomain())
                                        Vincule um dominio existente ou crie um novo sem sair da tarefa.
                                    @else
                                        Escolha um contrato ou uma proposta para liberar a criacao rapida de dominio.
                                    @endif
                                </p>

                                @error('taskForm.domain_id')
                                    <span class="mt-1 block text-xs text-red-600 dark:text-red-300">{{ $message }}</span>
                                @enderror
                            </div>

                            <label>
                                <span class="text-sm font-medium text-gray-700 dark:text-gray-200">Servico</span>
                                <select wire:model="taskForm.service_id" class="mt-1 block w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-950 shadow-sm outline-none transition focus:border-amber-500 focus:ring-2 focus:ring-amber-500/20 dark:border-white/10 dark:bg-gray-950 dark:text-white">
                                    <option value="">Sem servico</option>
                                    @foreach ($this->serviceOptions() as $id => $label)
                                        <option value="{{ $id }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </label>

                            <label>
                                <span class="text-sm font-medium text-gray-700 dark:text-gray-200">Periodo de referencia</span>
                                <input
                                    type="text"
                                    wire:model="taskForm.reference_period"
                                    placeholder="2026-05"
                                    class="mt-1 block w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-950 shadow-sm outline-none transition focus:border-amber-500 focus:ring-2 focus:ring-amber-500/20 dark:border-white/10 dark:bg-gray-950 dark:text-white"
                                />
                            </label>

                            <label class="md:col-span-2">
                                <span class="text-sm font-medium text-gray-700 dark:text-gray-200">Descricao</span>
                                <textarea wire:model="taskForm.description" rows="4" class="mt-1 block w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-950 shadow-sm outline-none transition focus:border-amber-500 focus:ring-2 focus:ring-amber-500/20 dark:border-white/10 dark:bg-gray-950 dark:text-white"></textarea>
                            </label>

                            <label class="md:col-span-2">
                                <span class="text-sm font-medium text-gray-700 dark:text-gray-200">Links externos</span>
                                <textarea wire:model="taskForm.external_links_text" rows="3" placeholder="Um link por linha" class="mt-1 block w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-950 shadow-sm outline-none transition focus:border-amber-500 focus:ring-2 focus:ring-amber-500/20 dark:border-white/10 dark:bg-gray-950 dark:text-white"></textarea>
                            </label>

                            @if ($conversionModalOpen)
                                <label class="md:col-span-2 flex items-center gap-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-3 text-sm text-amber-900 dark:border-amber-400/20 dark:bg-amber-400/10 dark:text-amber-100">
                                    <input type="checkbox" wire:model="conversionStartTimer" class="rounded border-amber-300 text-amber-600 focus:ring-amber-500" />
                                    Iniciar timer agora
                                </label>
                            @elseif (($taskForm['kanban_status'] ?? null) === \App\ActivityKanbanStatus::InProgress->value)
                                <label class="md:col-span-2 flex items-center gap-3 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-3 text-sm text-emerald-900 dark:border-emerald-400/20 dark:bg-emerald-400/10 dark:text-emerald-100">
                                    <input type="checkbox" wire:model="taskForm.start_timer" class="rounded border-emerald-300 text-emerald-600 focus:ring-emerald-500" />
                                    Iniciar timer ao salvar
                                </label>
                            @endif
                        </div>
                    </div>

                    <footer class="flex flex-col gap-3 border-t border-gray-200 px-5 py-4 dark:border-white/10 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            @if ($fullEditId)
                                <a href="{{ $this->fullEditUrl($fullEditId) }}" class="text-sm font-medium text-gray-500 transition hover:text-amber-700 dark:text-gray-400 dark:hover:text-amber-300">
                                    Abrir edicao completa
                                </a>
                            @endif
                        </div>

                        <div class="flex justify-end gap-2">
                            <button type="button" wire:click="{{ $conversionModalOpen ? 'cancelConversion' : 'closeTaskModal' }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 transition hover:bg-gray-50 dark:border-white/10 dark:text-gray-200 dark:hover:bg-white/10">
                                Cancelar
                            </button>

                            <button type="button" wire:click="{{ $conversionModalOpen ? 'confirmConversion' : 'saveTask' }}" wire:loading.attr="disabled" wire:target="{{ $conversionModalOpen ? 'confirmConversion' : 'saveTask' }}" class="inline-flex items-center gap-2 rounded-md bg-amber-500 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-amber-600 focus:outline-none focus:ring-2 focus:ring-amber-400 disabled:cursor-wait disabled:opacity-70">
                                <x-heroicon-o-arrow-path class="hidden h-4 w-4 animate-spin" wire:loading.class.remove="hidden" wire:target="{{ $conversionModalOpen ? 'confirmConversion' : 'saveTask' }}" />
                                <span>{{ $conversionModalOpen ? 'Mover para andamento' : 'Salvar' }}</span>
                            </button>
                        </div>
                    </footer>
                </section>
            </div>
        @endif

        @if ($quickDomainModalOpen)
            <div class="fixed inset-0 z-[60] flex items-center justify-center bg-gray-950/75 px-4 py-8">
                <section class="w-full max-w-lg overflow-hidden rounded-lg bg-white shadow-xl ring-1 ring-black/5 dark:bg-gray-900 dark:ring-white/10">
                    <div class="flex items-start justify-between gap-4 border-b border-gray-200 px-5 py-4 dark:border-white/10">
                        <div>
                            <h2 class="text-lg font-semibold text-gray-950 dark:text-white">Novo dominio</h2>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                Criacao rapida para continuar no fluxo da tarefa.
                            </p>
                        </div>

                        <button
                            type="button"
                            wire:click="closeQuickDomainModal"
                            class="rounded-md p-2 text-gray-400 transition hover:bg-gray-100 hover:text-gray-700 dark:hover:bg-white/10 dark:hover:text-gray-200"
                        >
                            <x-heroicon-o-x-mark class="h-5 w-5" />
                        </button>
                    </div>

                    <div class="space-y-4 px-5 py-5">
                        <label class="block">
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-200">Dominio</span>
                            <input
                                type="text"
                                wire:model="quickDomainForm.domain_name"
                                class="mt-1 block w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-950 shadow-sm outline-none transition focus:border-amber-500 focus:ring-2 focus:ring-amber-500/20 dark:border-white/10 dark:bg-gray-950 dark:text-white"
                            />
                            @error('quickDomainForm.domain_name')
                                <span class="mt-1 block text-xs text-red-600 dark:text-red-300">{{ $message }}</span>
                            @enderror
                        </label>

                        <div class="grid gap-4 md:grid-cols-2">
                            <label>
                                <span class="text-sm font-medium text-gray-700 dark:text-gray-200">Status</span>
                                <select wire:model="quickDomainForm.status" class="mt-1 block w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-950 shadow-sm outline-none transition focus:border-amber-500 focus:ring-2 focus:ring-amber-500/20 dark:border-white/10 dark:bg-gray-950 dark:text-white">
                                    @foreach ($this->domainStatusOptions() as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </label>

                            <label>
                                <span class="text-sm font-medium text-gray-700 dark:text-gray-200">Tipo de acesso</span>
                                <select wire:model="quickDomainForm.access_type" class="mt-1 block w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-950 shadow-sm outline-none transition focus:border-amber-500 focus:ring-2 focus:ring-amber-500/20 dark:border-white/10 dark:bg-gray-950 dark:text-white">
                                    @foreach ($this->domainAccessTypeOptions() as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </label>
                        </div>
                    </div>

                    <footer class="flex justify-end gap-2 border-t border-gray-200 px-5 py-4 dark:border-white/10">
                        <button type="button" wire:click="closeQuickDomainModal" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 transition hover:bg-gray-50 dark:border-white/10 dark:text-gray-200 dark:hover:bg-white/10">
                            Cancelar
                        </button>

                        <button type="button" wire:click="saveQuickDomain" wire:loading.attr="disabled" wire:target="saveQuickDomain" class="inline-flex items-center gap-2 rounded-md bg-amber-500 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-amber-600 focus:outline-none focus:ring-2 focus:ring-amber-400 disabled:cursor-wait disabled:opacity-70">
                            <x-heroicon-o-arrow-path class="hidden h-4 w-4 animate-spin" wire:loading.class.remove="hidden" wire:target="saveQuickDomain" />
                            <span>Criar dominio</span>
                        </button>
                    </footer>
                </section>
            </div>
        @endif
    </div>
</x-filament-panels::page>
