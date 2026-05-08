<?php

namespace App\Support\RemoteServers;

use App\Models\Domain;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\DirectoryAttributes;
use League\Flysystem\FileAttributes;
use League\Flysystem\StorageAttributes;
use RuntimeException;

class RemoteFileBrowser
{
    public function __construct(
        protected RemoteConnectionFactory $connectionFactory,
    ) {}

    /**
     * @return list<array{name: string, path: string, type: string, size: ?int, last_modified: ?int, mime_type: ?string, extension: ?string}>
     */
    public function listDirectory(Domain $domain, string $directory = ''): array
    {
        $path = $this->normalizeDirectory($directory);

        return Collection::make($this->connectionFactory->makeFilesystemOperator($domain)->listContents($path, false))
            ->map(fn (StorageAttributes $item): array => [
                'name' => basename($item->path()),
                'path' => $item->path(),
                'type' => $item instanceof DirectoryAttributes ? 'dir' : 'file',
                'size' => $item instanceof FileAttributes ? $item->fileSize() : null,
                'last_modified' => $item->lastModified(),
                'mime_type' => $item instanceof FileAttributes ? $item->mimeType() : null,
                'extension' => $item instanceof FileAttributes ? strtolower((string) pathinfo($item->path(), PATHINFO_EXTENSION)) : null,
            ])
            ->sort(function (array $first, array $second): int {
                if ($first['type'] !== $second['type']) {
                    return $first['type'] === 'dir' ? -1 : 1;
                }

                return strnatcasecmp($first['name'], $second['name']);
            })
            ->values()
            ->all();
    }

    public function readFile(Domain $domain, string $path): string
    {
        return $this->connectionFactory->makeFilesystemOperator($domain)->read($this->normalizePath($path));
    }

    public function writeFile(Domain $domain, string $path, string $contents): void
    {
        $this->connectionFactory->makeFilesystemOperator($domain)->write($this->normalizePath($path), $contents);
    }

    public function delete(Domain $domain, string $path, string $type): void
    {
        $normalizedPath = $this->normalizePath($path);
        $filesystem = $this->connectionFactory->makeFilesystemOperator($domain);

        if ($type === 'dir') {
            $filesystem->deleteDirectory($normalizedPath);

            return;
        }

        $filesystem->delete($normalizedPath);
    }

    public function createDirectory(Domain $domain, string $currentDirectory, string $name): void
    {
        $this->connectionFactory->makeFilesystemOperator($domain)->createDirectory(
            $this->joinPath($currentDirectory, $name),
        );
    }

    /**
     * @param  list<string>  $localFiles
     */
    public function uploadFiles(Domain $domain, string $currentDirectory, array $localFiles): void
    {
        $filesystem = $this->connectionFactory->makeFilesystemOperator($domain);

        foreach ($localFiles as $localFile) {
            $filename = basename($localFile);
            $targetPath = $this->joinPath($currentDirectory, $filename);
            $fullPath = Storage::disk('local')->path($localFile);
            $stream = fopen($fullPath, 'rb');

            if (! is_resource($stream)) {
                throw new RuntimeException("Não foi possível abrir o arquivo temporário {$filename}.");
            }

            try {
                $filesystem->writeStream($targetPath, $stream);
            } finally {
                fclose($stream);
                Storage::disk('local')->delete($localFile);
            }
        }
    }

    public function normalizeDirectory(?string $directory): string
    {
        $directory = trim((string) $directory);

        if ($directory === '' || $directory === '/') {
            return '';
        }

        return trim($directory, '/');
    }

    public function normalizePath(string $path): string
    {
        $path = trim($path);

        if ($path === '') {
            throw new RuntimeException('O caminho remoto informado é inválido.');
        }

        return trim($path, '/');
    }

    public function parentDirectory(string $directory): string
    {
        $directory = $this->normalizeDirectory($directory);

        if ($directory === '') {
            return '';
        }

        $parent = dirname($directory);

        return $parent === '.' ? '' : trim($parent, '/');
    }

    public function joinPath(string $directory, string $name): string
    {
        $name = trim($name);

        if ($name === '') {
            throw new RuntimeException('O nome informado é obrigatório.');
        }

        $directory = $this->normalizeDirectory($directory);

        return $directory === ''
            ? ltrim($name, '/')
            : "{$directory}/".ltrim($name, '/');
    }
}
