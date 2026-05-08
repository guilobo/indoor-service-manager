<?php

namespace App\Filament\Resources\Domains\Pages;

use App\Filament\Resources\Domains\DomainResource;
use App\Models\Domain;
use App\Support\RemoteServers\RemoteConnectionFactory;
use App\Support\RemoteServers\RemoteFileBrowser;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Number;
use Throwable;

class ManageDomainFiles extends Page
{
    use InteractsWithRecord;

    protected static string $resource = DomainResource::class;

    protected string $view = 'filament.resources.domains.pages.manage-domain-files';

    public string $currentPath = '';

    /**
     * @var list<array{name: string, path: string, type: string, size: ?int, last_modified: ?int, mime_type: ?string, extension: ?string}>
     */
    public array $entries = [];

    public ?string $editingFilePath = null;

    public string $editingFileContents = '';

    public ?string $browserError = null;

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);

        abort_unless(static::getResource()::canEdit($this->getRecord()), 403);

        $this->currentPath = app(RemoteFileBrowser::class)->normalizeDirectory($this->getRecord()->access_start_path);

        if ($this->getRecord()->canBrowseFiles()) {
            $this->refreshEntries();
        }
    }

    public function getTitle(): string|Htmlable
    {
        return 'Arquivos remotos';
    }

    public function getHeading(): string|Htmlable
    {
        return "Arquivos remotos de {$this->getRecord()->domain_name}";
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('backToDomain')
                ->label('Voltar ao domínio')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(fn (): string => DomainResource::getUrl('edit', ['record' => $this->getRecord()])),
            Action::make('testAccess')
                ->label('Testar acesso')
                ->icon('heroicon-o-bolt')
                ->color('gray')
                ->action(fn (): Notification => $this->sendTestConnectionNotification()),
            Action::make('createDirectory')
                ->label('Nova pasta')
                ->icon('heroicon-o-folder-plus')
                ->visible(fn (): bool => $this->getRecord()->canBrowseFiles())
                ->schema([
                    TextInput::make('name')
                        ->label('Nome da pasta')
                        ->required()
                        ->maxLength(255),
                ])
                ->action(function (array $data): void {
                    app(RemoteFileBrowser::class)->createDirectory($this->getRecord(), $this->currentPath, $data['name']);
                    $this->refreshEntries();

                    Notification::make()
                        ->success()
                        ->title('Pasta criada')
                        ->send();
                }),
            Action::make('uploadFiles')
                ->label('Upload')
                ->icon('heroicon-o-arrow-up-tray')
                ->visible(fn (): bool => $this->getRecord()->canBrowseFiles())
                ->schema([
                    FileUpload::make('files')
                        ->label('Arquivos')
                        ->multiple()
                        ->disk('local')
                        ->directory('tmp/domain-uploads')
                        ->required(),
                ])
                ->action(function (array $data): void {
                    app(RemoteFileBrowser::class)->uploadFiles($this->getRecord(), $this->currentPath, $data['files']);
                    $this->refreshEntries();

                    Notification::make()
                        ->success()
                        ->title('Upload concluído')
                        ->send();
                }),
        ];
    }

    public function openDirectory(string $path): void
    {
        $this->currentPath = app(RemoteFileBrowser::class)->normalizeDirectory($path);
        $this->editingFilePath = null;
        $this->editingFileContents = '';
        $this->refreshEntries();
    }

    public function openParentDirectory(): void
    {
        $this->currentPath = app(RemoteFileBrowser::class)->parentDirectory($this->currentPath);
        $this->editingFilePath = null;
        $this->editingFileContents = '';
        $this->refreshEntries();
    }

    public function startEditingFile(string $path): void
    {
        try {
            $this->editingFilePath = $path;
            $this->editingFileContents = app(RemoteFileBrowser::class)->readFile($this->getRecord(), $path);
        } catch (Throwable $exception) {
            Notification::make()
                ->danger()
                ->title('Não foi possível abrir o arquivo')
                ->body($exception->getMessage())
                ->send();
        }
    }

    public function cancelEditingFile(): void
    {
        $this->editingFilePath = null;
        $this->editingFileContents = '';
    }

    public function saveEditingFile(): void
    {
        if ($this->editingFilePath === null) {
            return;
        }

        app(RemoteFileBrowser::class)->writeFile($this->getRecord(), $this->editingFilePath, $this->editingFileContents);
        $this->refreshEntries();

        Notification::make()
            ->success()
            ->title('Arquivo salvo')
            ->send();
    }

    public function deleteEntry(string $path, string $type): void
    {
        app(RemoteFileBrowser::class)->delete($this->getRecord(), $path, $type);

        if ($this->editingFilePath === $path) {
            $this->cancelEditingFile();
        }

        $this->refreshEntries();

        Notification::make()
            ->success()
            ->title($type === 'dir' ? 'Pasta excluída' : 'Arquivo excluído')
            ->send();
    }

    /**
     * @return list<array{label: string, path: string}>
     */
    public function getBreadcrumbSegments(): array
    {
        $currentPath = app(RemoteFileBrowser::class)->normalizeDirectory($this->currentPath);

        if ($currentPath === '') {
            return [];
        }

        $segments = explode('/', $currentPath);
        $path = '';

        return collect($segments)
            ->map(function (string $segment) use (&$path): array {
                $path = $path === '' ? $segment : "{$path}/{$segment}";

                return [
                    'label' => $segment,
                    'path' => $path,
                ];
            })
            ->all();
    }

    public function formatSize(?int $size): string
    {
        return $size === null ? '-' : Number::fileSize($size);
    }

    /**
     * @param  array{name: string, path: string, type: string, size: ?int, last_modified: ?int, mime_type: ?string, extension: ?string}  $entry
     */
    public function canEditEntry(array $entry): bool
    {
        if ($entry['type'] !== 'file') {
            return false;
        }

        if (($entry['size'] ?? 0) > 512000) {
            return false;
        }

        return in_array($entry['extension'], [
            'env',
            'htaccess',
            'ini',
            'json',
            'js',
            'css',
            'html',
            'htm',
            'md',
            'php',
            'txt',
            'xml',
            'yml',
            'yaml',
            'log',
        ], true);
    }

    protected function refreshEntries(): void
    {
        try {
            $this->entries = app(RemoteFileBrowser::class)->listDirectory($this->getRecord(), $this->currentPath);
            $this->browserError = null;
        } catch (Throwable $exception) {
            $this->entries = [];
            $this->browserError = $exception->getMessage();
        }
    }

    protected function sendTestConnectionNotification(): Notification
    {
        try {
            $message = app(RemoteConnectionFactory::class)->testConnection($this->getRecord());

            return Notification::make()
                ->success()
                ->title('Acesso validado')
                ->body($message)
                ->send();
        } catch (Throwable $exception) {
            return Notification::make()
                ->danger()
                ->title('Falha ao validar acesso')
                ->body($exception->getMessage())
                ->send();
        }
    }

    public function getRecord(): Domain
    {
        /** @var Domain $record */
        $record = $this->record;

        return $record;
    }
}
