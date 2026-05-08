<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class Gel5FileController extends Controller
{
    public function __invoke(string $path): StreamedResponse
    {
        $localPath = $this->localPath($path);

        try {
            $stream = Storage::disk('public')->readStream($localPath);

            if (! is_resource($stream)) {
                abort(404);
            }

            $headers = [
                'Content-Type' => Storage::disk('public')->mimeType($localPath) ?? 'application/octet-stream',
                'Cache-Control' => 'public, max-age=31536000, immutable',
            ];

            $fileSize = Storage::disk('public')->size($localPath);

            if ($fileSize > 0) {
                $headers['Content-Length'] = (string) $fileSize;
            }

            return response()->stream(function () use ($stream): void {
                fpassthru($stream);

                if (is_resource($stream)) {
                    fclose($stream);
                }
            }, 200, $headers);
        } catch (Throwable) {
            abort(404);
        }
    }

    protected function localPath(string $path): string
    {
        $decodedPath = base64_decode(strtr($path, '-_', '+/'), true);

        if (is_string($decodedPath) && filled($decodedPath)) {
            $path = $decodedPath;
        }

        $root = trim((string) config('filesystems.disks.public.root'), '/');
        $path = trim($path, '/');

        if ($root !== '' && str_starts_with($path, $root.'/')) {
            return ltrim(substr($path, strlen($root)), '/');
        }

        return $path;
    }
}
