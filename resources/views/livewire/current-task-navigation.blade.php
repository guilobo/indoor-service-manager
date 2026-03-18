<div wire:poll.1s="refreshTask">
    @if ($task && $taskUrl)
        <ul class="fi-sidebar-nav-groups">
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
