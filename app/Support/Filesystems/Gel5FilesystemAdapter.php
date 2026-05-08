<?php

namespace App\Support\Filesystems;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use League\Flysystem\Config;
use League\Flysystem\DirectoryAttributes;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\StorageAttributes;
use League\Flysystem\UnableToCheckExistence;
use League\Flysystem\UnableToCopyFile;
use League\Flysystem\UnableToCreateDirectory;
use League\Flysystem\UnableToDeleteDirectory;
use League\Flysystem\UnableToDeleteFile;
use League\Flysystem\UnableToMoveFile;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\UnableToRetrieveMetadata;
use League\Flysystem\UnableToSetVisibility;
use League\Flysystem\UnableToWriteFile;
use RuntimeException;
use Throwable;

class Gel5FilesystemAdapter implements FilesystemAdapter
{
    public function __construct(
        protected string $endpoint,
        protected ?string $apiKey,
        protected string $root = '',
        protected ?string $publicUrl = null,
    ) {
        $this->endpoint = rtrim($endpoint, "? \t\n\r\0\x0B");
        $this->root = $this->normalizePath($root);
        $this->publicUrl = $publicUrl === null ? null : rtrim($publicUrl, '/');
    }

    public function fileExists(string $path): bool
    {
        try {
            return $this->existsAs($path, StorageAttributes::TYPE_FILE);
        } catch (Throwable $exception) {
            throw UnableToCheckExistence::forLocation($path, $exception);
        }
    }

    public function directoryExists(string $path): bool
    {
        try {
            return $this->existsAs($path, StorageAttributes::TYPE_DIRECTORY);
        } catch (Throwable $exception) {
            throw UnableToCheckExistence::forLocation($path, $exception);
        }
    }

    public function write(string $path, string $contents, Config $config): void
    {
        try {
            $this->postJson('key/upsert', [
                'path' => $this->remotePath($path),
                'type' => 'file',
                'content' => $contents,
                'overwrite' => true,
            ]);
        } catch (Throwable $exception) {
            throw UnableToWriteFile::atLocation($path, $exception->getMessage(), $exception);
        }
    }

    public function writeStream(string $path, $contents, Config $config): void
    {
        try {
            $remotePath = $this->remotePath($path);

            $response = $this->request()
                ->asMultipart()
                ->attach('files', $contents, basename($remotePath))
                ->post($this->endpointFor('key/upload'), [
                    'path' => $this->root,
                    'fileSubPath' => $this->normalizePath(dirname($path) === '.' ? '' : dirname($path)),
                    'customName' => basename($path),
                    'overwrite' => '1',
                ]);

            $this->ensureSuccessful($response, 'key/upload');
        } catch (Throwable $exception) {
            throw UnableToWriteFile::atLocation($path, $exception->getMessage(), $exception);
        }
    }

    public function read(string $path): string
    {
        try {
            $response = $this->postJson('key/read', [
                'path' => $this->remotePath($path),
            ]);

            $json = $response->json();

            if (is_array($json)) {
                if (isset($json['content_base64']) && is_string($json['content_base64'])) {
                    $decoded = base64_decode($json['content_base64'], true);

                    if ($decoded === false) {
                        throw new RuntimeException('Invalid base64 content returned by Gel5 files API.');
                    }

                    return $decoded;
                }

                if (isset($json['content']) && is_string($json['content'])) {
                    if (($json['encoding'] ?? null) === 'base64') {
                        $decoded = base64_decode($json['content'], true);

                        if ($decoded === false) {
                            throw new RuntimeException('Invalid base64 content returned by Gel5 files API.');
                        }

                        return $decoded;
                    }

                    return $json['content'];
                }
            }

            return $response->body();
        } catch (Throwable $exception) {
            throw UnableToReadFile::fromLocation($path, $exception->getMessage(), $exception);
        }
    }

    public function readStream(string $path)
    {
        $stream = fopen('php://temp', 'w+b');

        if ($stream === false) {
            throw UnableToReadFile::fromLocation($path, 'Unable to open temporary stream.');
        }

        fwrite($stream, $this->read($path));
        rewind($stream);

        return $stream;
    }

    public function delete(string $path): void
    {
        try {
            $this->postJson('key/delete', [
                'path' => $this->remotePath($path),
            ]);
        } catch (Throwable $exception) {
            throw UnableToDeleteFile::atLocation($path, $exception->getMessage(), $exception);
        }
    }

    public function deleteDirectory(string $path): void
    {
        try {
            $this->postJson('key/delete', [
                'path' => $this->remotePath($path),
            ]);
        } catch (Throwable $exception) {
            throw UnableToDeleteDirectory::atLocation($path, $exception->getMessage(), $exception);
        }
    }

    public function createDirectory(string $path, Config $config): void
    {
        try {
            $this->postJson('key/upsert', [
                'path' => $this->remotePath($path),
                'type' => 'directory',
                'overwrite' => true,
            ]);
        } catch (Throwable $exception) {
            throw UnableToCreateDirectory::atLocation($path, $exception->getMessage(), $exception);
        }
    }

    public function setVisibility(string $path, string $visibility): void
    {
        if ($visibility !== 'public') {
            throw UnableToSetVisibility::atLocation($path, 'Gel5 files are served by the configured public URL.');
        }
    }

    public function visibility(string $path): FileAttributes
    {
        return new FileAttributes($path, visibility: 'public');
    }

    public function mimeType(string $path): FileAttributes
    {
        $meta = $this->metadata($path, 'mime_type');

        return new FileAttributes($path, mimeType: $this->stringValue($meta, ['mime_type', 'mimeType']));
    }

    public function lastModified(string $path): FileAttributes
    {
        $meta = $this->metadata($path, 'last_modified');

        return new FileAttributes($path, lastModified: $this->timestampValue($meta));
    }

    public function fileSize(string $path): FileAttributes
    {
        $meta = $this->metadata($path, 'file_size');

        return new FileAttributes($path, fileSize: $this->integerValue($meta, ['file_size', 'size']));
    }

    public function listContents(string $path, bool $deep): iterable
    {
        $response = $this->postJson('key/list', [
            'path' => $this->remotePath($path),
            'deep' => $deep,
        ]);

        $json = $response->json();
        $items = is_array($json) ? ($json['items'] ?? $json) : [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $itemPath = $this->localPath((string) ($item['path'] ?? ''));
            $type = $this->normalizeType($item['type'] ?? null);
            $lastModified = $this->timestampValue($item);

            if ($type === StorageAttributes::TYPE_DIRECTORY) {
                yield new DirectoryAttributes($itemPath, 'public', $lastModified);

                continue;
            }

            yield new FileAttributes(
                $itemPath,
                $this->integerValue($item, ['file_size', 'size']),
                'public',
                $lastModified,
                $this->stringValue($item, ['mime_type', 'mimeType'])
            );
        }
    }

    public function move(string $source, string $destination, Config $config): void
    {
        try {
            $this->postJson('key/rename', [
                'path' => $this->remotePath($source),
                'newPath' => $this->remotePath($destination),
            ]);
        } catch (Throwable $exception) {
            throw UnableToMoveFile::fromLocationTo($source, $destination, $exception);
        }
    }

    public function copy(string $source, string $destination, Config $config): void
    {
        try {
            $this->postJson('key/copy', [
                'path' => $this->remotePath($source),
                'newPath' => $this->remotePath($destination),
            ]);
        } catch (Throwable $exception) {
            throw UnableToCopyFile::fromLocationTo($source, $destination, $exception);
        }
    }

    public function getUrl(string $path): string
    {
        if ($this->publicUrl === null) {
            throw new RuntimeException('The Gel5 filesystem disk does not have a public URL configured.');
        }

        $remotePath = $this->remotePath($path);

        if (str_ends_with($this->publicUrl, '/media')) {
            return $this->publicUrl.'/'.rtrim(strtr(base64_encode($remotePath), '+/', '-_'), '=');
        }

        return $this->publicUrl.'/'.$remotePath;
    }

    /**
     * @return array<string, mixed>
     */
    protected function metadata(string $path, string $type): array
    {
        try {
            $meta = $this->fetchMeta($path);
        } catch (Throwable $exception) {
            throw UnableToRetrieveMetadata::create($path, $type, $exception->getMessage(), $exception);
        }

        if (($meta['exists'] ?? true) === false) {
            throw UnableToRetrieveMetadata::create($path, $type, 'Path not found.');
        }

        return $meta;
    }

    protected function existsAs(string $path, string $expectedType): bool
    {
        try {
            $meta = $this->fetchMeta($path);
        } catch (RuntimeException $exception) {
            if ($exception->getCode() === 404) {
                return false;
            }

            throw $exception;
        }

        if (($meta['exists'] ?? true) === false) {
            return false;
        }

        return $this->normalizeType($meta['type'] ?? null) === $expectedType;
    }

    /**
     * @return array<string, mixed>
     */
    protected function fetchMeta(string $path): array
    {
        $response = $this->postJson('key/meta', [
            'path' => $this->remotePath($path),
        ]);

        $json = $response->json();

        if (! is_array($json)) {
            return [];
        }

        $meta = $json['item'] ?? $json['meta'] ?? $json;

        return is_array($meta) ? $meta : [];
    }

    protected function postJson(string $route, array $payload): Response
    {
        $response = $this->request()
            ->asJson()
            ->post($this->endpointFor($route), $payload);

        return $this->ensureSuccessful($response, $route);
    }

    protected function ensureSuccessful(Response $response, string $route): Response
    {
        if ($response->successful()) {
            return $response;
        }

        $message = $response->json('error') ?? $response->body() ?: "Gel5 files API request failed for {$route}.";

        throw new RuntimeException((string) $message, $response->status());
    }

    protected function request(): PendingRequest
    {
        if (blank($this->endpoint) || blank($this->apiKey)) {
            throw new RuntimeException('Gel5 files endpoint and API key must be configured.');
        }

        return Http::withHeaders([
            'X-API-Key' => $this->apiKey,
        ])->acceptJson();
    }

    protected function endpointFor(string $route): string
    {
        $separator = str_contains($this->endpoint, '?') ? '&' : '?';

        return $this->endpoint.$separator.'route='.rawurlencode($route);
    }

    protected function remotePath(string $path): string
    {
        $path = $this->normalizePath($path);

        return $this->root === '' ? $path : trim($this->root.'/'.$path, '/');
    }

    protected function localPath(string $path): string
    {
        $path = $this->normalizePath($path);

        if ($this->root !== '' && ($path === $this->root || str_starts_with($path, $this->root.'/'))) {
            return ltrim(substr($path, strlen($this->root)), '/');
        }

        return $path;
    }

    protected function normalizePath(string $path): string
    {
        $parts = [];

        foreach (explode('/', str_replace('\\', '/', $path)) as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }

            if ($part === '..') {
                if ($parts === []) {
                    throw new RuntimeException('Path traversal is not allowed.');
                }

                array_pop($parts);

                continue;
            }

            $parts[] = $part;
        }

        return implode('/', $parts);
    }

    protected function normalizeType(mixed $type): string
    {
        return in_array($type, ['dir', 'directory', 'folder'], true)
            ? StorageAttributes::TYPE_DIRECTORY
            : StorageAttributes::TYPE_FILE;
    }

    /**
     * @param  array<string, mixed>  $values
     * @param  list<string>  $keys
     */
    protected function integerValue(array $values, array $keys): ?int
    {
        foreach ($keys as $key) {
            if (isset($values[$key]) && is_numeric($values[$key])) {
                return (int) $values[$key];
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $values
     * @param  list<string>  $keys
     */
    protected function stringValue(array $values, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (isset($values[$key]) && is_string($values[$key])) {
                return $values[$key];
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $values
     */
    protected function timestampValue(array $values): ?int
    {
        $value = $values['last_modified'] ?? $values['lastModified'] ?? null;

        if (is_numeric($value)) {
            return (int) $value;
        }

        if (is_string($value)) {
            $timestamp = strtotime($value);

            return $timestamp === false ? null : $timestamp;
        }

        return null;
    }
}
