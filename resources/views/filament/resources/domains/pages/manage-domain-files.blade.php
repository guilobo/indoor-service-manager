<x-filament-panels::page>
    <div class="grid gap-6">
        <section class="overflow-hidden rounded-3xl border border-gray-200/80 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900">
            <div class="flex flex-col gap-4 border-b border-gray-200/80 bg-linear-to-r from-amber-50 via-white to-orange-50 px-6 py-5 dark:border-white/10 dark:from-gray-900 dark:via-gray-900 dark:to-gray-800 lg:flex-row lg:items-center lg:justify-between">
                <div class="space-y-2">
                    <p class="text-xs font-semibold uppercase tracking-[0.3em] text-amber-600 dark:text-amber-400">Domínio</p>
                    <div>
                        <h2 class="text-xl font-semibold text-gray-950 dark:text-white">{{ $this->getRecord()->domain_name }}</h2>
                        <p class="text-sm text-gray-600 dark:text-gray-300">
                            {{ strtoupper($this->getRecord()->access_type->value) }}
                            em
                            {{ $this->getRecord()->ftp_host ?: '-' }}
                            @if ($this->getRecord()->access_port)
                                :{{ $this->getRecord()->access_port }}
                            @endif
                        </p>
                    </div>
                </div>

                <div class="rounded-2xl border border-amber-200/80 bg-white/90 px-4 py-3 text-sm text-gray-600 shadow-sm dark:border-amber-500/20 dark:bg-white/5 dark:text-gray-300">
                    Caminho atual:
                    <span class="font-semibold text-gray-950 dark:text-white">{{ $currentPath !== '' ? "/{$currentPath}" : '/' }}</span>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-3 px-6 py-4">
                <button
                    type="button"
                    x-on:click="$wire.openDirectory('')"
                    class="rounded-full border border-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 transition hover:border-amber-300 hover:text-amber-700 dark:border-white/10 dark:text-gray-200 dark:hover:border-amber-400/50 dark:hover:text-amber-300"
                >
                    Raiz
                </button>

                @foreach ($this->getBreadcrumbSegments() as $breadcrumb)
                    <button
                        type="button"
                        x-on:click="$wire.openDirectory(@js($breadcrumb['path']))"
                        class="rounded-full border border-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 transition hover:border-amber-300 hover:text-amber-700 dark:border-white/10 dark:text-gray-200 dark:hover:border-amber-400/50 dark:hover:text-amber-300"
                    >
                        {{ $breadcrumb['label'] }}
                    </button>
                @endforeach

                @if ($currentPath !== '')
                    <button
                        type="button"
                        wire:click="openParentDirectory"
                        class="rounded-full border border-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 transition hover:border-amber-300 hover:text-amber-700 dark:border-white/10 dark:text-gray-200 dark:hover:border-amber-400/50 dark:hover:text-amber-300"
                    >
                        Subir um nível
                    </button>
                @endif
            </div>
        </section>

        @if (! $this->getRecord()->canBrowseFiles())
            <section class="rounded-3xl border border-dashed border-amber-300 bg-amber-50 px-6 py-8 text-sm text-amber-900 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200">
                A navegação remota foi habilitada apenas para conexões FTP e SFTP. Para domínios marcados como SSH, o botão "Testar acesso" continua validando as credenciais do servidor.
            </section>
        @else
            @if ($browserError)
                <section class="rounded-3xl border border-red-200 bg-red-50 px-6 py-4 text-sm text-red-800 dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-200">
                    {{ $browserError }}
                </section>
            @endif

            <section class="overflow-hidden rounded-3xl border border-gray-200/80 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900">
                <div class="border-b border-gray-200/80 px-6 py-4 dark:border-white/10">
                    <h3 class="text-base font-semibold text-gray-950 dark:text-white">Arquivos e pastas</h3>
                </div>

                @if (count($entries) === 0)
                    <div class="px-6 py-10 text-center text-sm text-gray-500 dark:text-gray-400">
                        Nenhum arquivo encontrado neste diretório.
                    </div>
                @else
                    <div class="divide-y divide-gray-200/80 dark:divide-white/10">
                        @foreach ($entries as $entry)
                            <div class="flex flex-col gap-4 px-6 py-4 lg:flex-row lg:items-center lg:justify-between" wire:key="entry-{{ md5($entry['path']) }}">
                                <div class="flex min-w-0 items-start gap-3">
                                    <div class="mt-0.5 flex h-10 w-10 items-center justify-center rounded-2xl {{ $entry['type'] === 'dir' ? 'bg-amber-100 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300' : 'bg-sky-100 text-sky-700 dark:bg-sky-500/10 dark:text-sky-300' }}">
                                        @if ($entry['type'] === 'dir')
                                            <x-heroicon-o-folder class="h-5 w-5" />
                                        @else
                                            <x-heroicon-o-document-text class="h-5 w-5" />
                                        @endif
                                    </div>

                                    <div class="min-w-0 space-y-1">
                                        <p class="truncate text-sm font-semibold text-gray-950 dark:text-white">{{ $entry['name'] }}</p>
                                        <p class="truncate text-xs text-gray-500 dark:text-gray-400">{{ $entry['path'] }}</p>
                                        <div class="flex flex-wrap gap-2 text-xs text-gray-500 dark:text-gray-400">
                                            <span>{{ $entry['type'] === 'dir' ? 'Pasta' : 'Arquivo' }}</span>
                                            <span>{{ $this->formatSize($entry['size']) }}</span>
                                            @if ($entry['last_modified'])
                                                <span>{{ \Illuminate\Support\Carbon::createFromTimestamp($entry['last_modified'])->format('d/m/Y H:i') }}</span>
                                            @endif
                                        </div>
                                    </div>
                                </div>

                                <div class="flex flex-wrap items-center gap-2">
                                    @if ($entry['type'] === 'dir')
                                        <button
                                            type="button"
                                            x-on:click="$wire.openDirectory(@js($entry['path']))"
                                            class="rounded-full bg-amber-500 px-3 py-1.5 text-sm font-medium text-white transition hover:bg-amber-600"
                                        >
                                            Abrir
                                        </button>
                                    @elseif ($this->canEditEntry($entry))
                                        <button
                                            type="button"
                                            x-on:click="$wire.startEditingFile(@js($entry['path']))"
                                            class="rounded-full bg-sky-500 px-3 py-1.5 text-sm font-medium text-white transition hover:bg-sky-600"
                                        >
                                            Editar
                                        </button>
                                    @endif

                                    <button
                                        type="button"
                                        x-on:click="$wire.deleteEntry(@js($entry['path']), @js($entry['type']))"
                                        wire:confirm="Tem certeza que deseja excluir este item?"
                                        class="rounded-full border border-red-200 px-3 py-1.5 text-sm font-medium text-red-700 transition hover:border-red-300 hover:bg-red-50 dark:border-red-500/30 dark:text-red-200 dark:hover:bg-red-500/10"
                                    >
                                        Excluir
                                    </button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </section>

            @if ($editingFilePath)
                <section class="overflow-hidden rounded-3xl border border-gray-200/80 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900">
                    <div class="flex flex-col gap-3 border-b border-gray-200/80 px-6 py-4 dark:border-white/10 lg:flex-row lg:items-center lg:justify-between">
                        <div>
                            <h3 class="text-base font-semibold text-gray-950 dark:text-white">Editar arquivo</h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400">{{ $editingFilePath }}</p>
                        </div>

                        <div class="flex flex-wrap gap-2">
                            <button
                                type="button"
                                wire:click="cancelEditingFile"
                                class="rounded-full border border-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 transition hover:border-gray-300 dark:border-white/10 dark:text-gray-200"
                            >
                                Cancelar
                            </button>
                            <button
                                type="button"
                                wire:click="saveEditingFile"
                                class="rounded-full bg-emerald-500 px-3 py-1.5 text-sm font-medium text-white transition hover:bg-emerald-600"
                            >
                                Salvar
                            </button>
                        </div>
                    </div>

                    <div class="px-6 py-5">
                        <textarea
                            wire:model="editingFileContents"
                            rows="20"
                            class="block min-h-80 w-full rounded-3xl border border-gray-200 bg-gray-950 px-4 py-4 font-mono text-sm text-emerald-100 shadow-inner outline-none ring-0 placeholder:text-gray-500 focus:border-amber-400 focus:ring-0 dark:border-white/10"
                        ></textarea>
                    </div>
                </section>
            @endif
        @endif
    </div>
</x-filament-panels::page>
