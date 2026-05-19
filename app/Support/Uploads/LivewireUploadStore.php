<?php

namespace App\Support\Uploads;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use RuntimeException;
use Throwable;

class LivewireUploadStore
{
    public static function isSerializedTemporaryUpload(string $path): bool
    {
        return str_starts_with($path, 'livewire-file:');
    }

    public static function isTemporaryUpload(mixed $upload): bool
    {
        return $upload instanceof TemporaryUploadedFile
            || (is_string($upload) && self::isSerializedTemporaryUpload($upload));
    }

    public static function storePublicly(mixed $upload, string $directory, bool $failWhenMissing = true): ?string
    {
        $filename = $upload instanceof TemporaryUploadedFile
            ? $upload->getFilename()
            : Str::after((string) $upload, 'livewire-file:');

        if (blank($filename)) {
            return null;
        }

        $extension = $upload instanceof TemporaryUploadedFile
            ? $upload->getClientOriginalExtension()
            : pathinfo($filename, PATHINFO_EXTENSION);
        $storedName = (string) Str::ulid().($extension === '' ? '' : ".{$extension}");
        $storedPath = trim($directory.'/'.$storedName, '/');

        try {
            $temporaryFile = $upload instanceof TemporaryUploadedFile
                ? $upload
                : TemporaryUploadedFile::createFromLivewire($filename);

            if ($temporaryFile->exists()) {
                $storedFile = retry(
                    3,
                    fn (): string|false => $temporaryFile->storePubliclyAs($directory, $storedName, 'public'),
                    500,
                );

                $temporaryFile->delete();

                if (is_string($storedFile)) {
                    return $storedFile;
                }

                if ($failWhenMissing) {
                    throw new RuntimeException('Nao foi possivel salvar o upload temporario do Livewire no disco publico.');
                }

                return null;
            }
        } catch (Throwable $exception) {
            if ($failWhenMissing) {
                throw $exception;
            }
        }

        if (! is_string($upload)) {
            if ($failWhenMissing) {
                throw new RuntimeException('Nao foi possivel salvar o upload temporario do Livewire no disco publico.');
            }

            return null;
        }

        $legacyTemporaryPath = 'livewire-tmp/'.$filename;

        try {
            if (Storage::disk('public')->exists($legacyTemporaryPath)) {
                Storage::disk('public')->move($legacyTemporaryPath, $storedPath);

                return $storedPath;
            }
        } catch (Throwable $exception) {
            if ($failWhenMissing) {
                throw $exception;
            }
        }

        if ($failWhenMissing) {
            throw new RuntimeException('Nao foi possivel salvar o upload temporario do Livewire no disco publico.');
        }

        return null;
    }
}
