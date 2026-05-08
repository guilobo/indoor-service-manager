<?php

namespace App\Support\Uploads;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use RuntimeException;

class LivewireUploadStore
{
    public static function isSerializedTemporaryUpload(string $path): bool
    {
        return str_starts_with($path, 'livewire-file:');
    }

    public static function storePublicly(string $serializedPath, string $directory, bool $failWhenMissing = true): ?string
    {
        $filename = Str::after($serializedPath, 'livewire-file:');

        if (blank($filename)) {
            return null;
        }

        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $storedName = (string) Str::ulid().($extension === '' ? '' : ".{$extension}");
        $storedPath = trim($directory.'/'.$storedName, '/');

        try {
            $temporaryFile = TemporaryUploadedFile::createFromLivewire($filename);

            if ($temporaryFile->exists()) {
                $storedFile = $temporaryFile->storePubliclyAs($directory, $storedName, 'public');
                $temporaryFile->delete();

                if (is_string($storedFile)) {
                    return $storedFile;
                }

                if ($failWhenMissing) {
                    throw new RuntimeException('Nao foi possivel salvar o upload temporario do Livewire no disco publico.');
                }

                return null;
            }
        } catch (RuntimeException $exception) {
            if ($failWhenMissing) {
                throw $exception;
            }
        }

        $legacyTemporaryPath = 'livewire-tmp/'.$filename;

        try {
            if (Storage::disk('public')->exists($legacyTemporaryPath)) {
                Storage::disk('public')->move($legacyTemporaryPath, $storedPath);

                return $storedPath;
            }
        } catch (RuntimeException $exception) {
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
