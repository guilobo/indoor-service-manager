<div wire:poll.15s="refreshTask">
    @if ($task && $taskUrl)
        <ul
            wire:key="current-task-navigation-{{ $task->getKey() }}"
            x-data="{
                elapsedSeconds: @js($elapsedSeconds ?? 0),
                interval: null,
                formatTimer(seconds) {
                    const safeSeconds = Math.max(Number(seconds) || 0, 0)
                    const hours = Math.floor(safeSeconds / 3600).toString().padStart(2, '0')
                    const minutes = Math.floor((safeSeconds % 3600) / 60).toString().padStart(2, '0')
                    const remainingSeconds = Math.floor(safeSeconds % 60).toString().padStart(2, '0')

                    return `${hours}:${minutes}:${remainingSeconds}`
                },
                updateTimer() {
                    const label = this.$el.querySelector('[data-current-task-timer] .fi-badge-label')

                    if (label) {
                        label.textContent = this.formatTimer(this.elapsedSeconds)
                    }
                },
                startTimer() {
                    this.updateTimer()
                    this.interval = setInterval(() => {
                        this.elapsedSeconds++
                        this.updateTimer()
                    }, 1000)
                },
            }"
            x-init="startTimer()"
            class="fi-sidebar-nav-groups"
        >
            <li class="fi-sidebar-group">
                <div
                    x-show="$store.sidebar.isOpen"
                    x-transition:enter="fi-transition-enter"
                    x-transition:enter-start="fi-transition-enter-start"
                    x-transition:enter-end="fi-transition-enter-end"
                    class="fi-sidebar-group-btn"
                >
                    <span class="fi-sidebar-group-label">
                        Operacao
                    </span>
                </div>

                <ul
                    x-show="$store.sidebar.isOpen"
                    x-transition:enter="fi-transition-enter"
                    x-transition:enter-start="fi-transition-enter-start"
                    x-transition:enter-end="fi-transition-enter-end"
                    class="fi-sidebar-group-items"
                >
                    <x-filament-panels::sidebar.item
                        data-current-task-timer
                        :badge="$elapsedTime"
                        badge-color="primary"
                        :badge-tooltip="'Tempo em andamento'"
                        :icon="\Filament\Support\Icons\Heroicon::OutlinedBolt"
                        :url="$taskUrl"
                    >
                        Tarefa em andamento
                    </x-filament-panels::sidebar.item>
                </ul>
            </li>
        </ul>
    @endif
</div>
