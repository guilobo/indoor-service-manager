<?php

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\UnableToWriteFile;

beforeEach(function (): void {
    config()->set('filesystems.disks.public', [
        'driver' => 'gel5',
        'endpoint' => 'https://files.test/api/index.php',
        'key' => 'test-key',
        'root' => 'indoor-service-manager',
        'url' => 'https://files.test/storage',
        'visibility' => 'public',
        'throw' => false,
        'report' => false,
    ]);

    Storage::forgetDisk('public');
    Http::preventStrayRequests();
});

it('stores file contents through the Gel5 upsert endpoint', function (): void {
    Http::fake([
        'files.test/*' => Http::response(['success' => true]),
    ]);

    expect(Storage::disk('public')->put('contracts/files/a.txt', 'Hello'))->toBeTrue();

    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
        && $request->url() === 'https://files.test/api/index.php?route=key%2Fupsert'
        && $request->hasHeader('X-API-Key', 'test-key')
        && $request['path'] === 'indoor-service-manager/contracts/files/a.txt'
        && $request['type'] === 'file'
        && $request['content'] === 'Hello'
        && $request['overwrite'] === true);
});

it('stores streams through the Gel5 upload endpoint', function (): void {
    Http::fake([
        'files.test/*' => Http::response(['success' => true]),
    ]);

    $stream = fopen('php://temp', 'w+b');
    fwrite($stream, 'image-bytes');
    rewind($stream);

    expect(Storage::disk('public')->put('activities/images/photo.jpg', $stream))->toBeTrue();

    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
        && $request->url() === 'https://files.test/api/index.php?route=key%2Fupload'
        && $request->hasHeader('X-API-Key', 'test-key')
        && $request->hasFile('files', filename: 'photo.jpg')
        && str_contains($request->body(), 'name="path"')
        && str_contains($request->body(), 'indoor-service-manager')
        && str_contains($request->body(), 'name="fileSubPath"')
        && str_contains($request->body(), 'activities/images')
        && str_contains($request->body(), 'name="customName"')
        && str_contains($request->body(), 'photo.jpg'));

    fclose($stream);
});

it('deletes and moves remote files through the Gel5 key endpoints', function (): void {
    Http::fake([
        'files.test/*' => Http::response(['success' => true]),
    ]);

    expect(Storage::disk('public')->delete('contracts/files/a.txt'))->toBeTrue();
    Storage::disk('public')->move('contracts/files/b.txt', 'contracts/files/c.txt');

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://files.test/api/index.php?route=key%2Fdelete'
        && $request['path'] === 'indoor-service-manager/contracts/files/a.txt');

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://files.test/api/index.php?route=key%2Frename'
        && $request['path'] === 'indoor-service-manager/contracts/files/b.txt'
        && $request['newPath'] === 'indoor-service-manager/contracts/files/c.txt');
});

it('generates public URLs with the configured remote root', function (): void {
    expect(Storage::disk('public')->url('contracts/files/a.pdf'))
        ->toBe('https://files.test/storage/indoor-service-manager/contracts/files/a.pdf');
});

it('generates extensionless media URLs for local delivery routes', function (): void {
    config()->set('filesystems.disks.public.url', 'http://indoor.service.manager/media');
    Storage::forgetDisk('public');

    expect(Storage::disk('public')->url('contracts/files/a.pdf'))
        ->toBe('http://indoor.service.manager/media/aW5kb29yLXNlcnZpY2UtbWFuYWdlci9jb250cmFjdHMvZmlsZXMvYS5wZGY');
});

it('serves Gel5 files through the local delivery route', function (): void {
    Http::fake(function (Request $request) {
        if (str_contains($request->url(), 'route=key%2Fread')) {
            return Http::response([
                'content' => 'Hello from Gel5',
            ]);
        }

        return Http::response([
            'item' => [
                'exists' => true,
                'type' => 'file',
                'size' => 15,
                'mime_type' => 'text/plain',
            ],
        ]);
    });

    $response = $this->get('/media/indoor-service-manager/contracts/files/a.txt')
        ->assertSuccessful()
        ->assertHeader('content-type', 'text/plain; charset=UTF-8');

    expect($response->streamedContent())->toBe('Hello from Gel5');
});

it('returns false for failed writes unless the disk is configured to throw', function (): void {
    Http::fake([
        'files.test/*' => Http::response(['error' => 'Upload failed'], 500),
    ]);

    expect(Storage::disk('public')->put('contracts/files/a.txt', 'Hello'))->toBeFalse();

    config()->set('filesystems.disks.public.throw', true);
    Storage::forgetDisk('public');

    expect(fn (): bool => Storage::disk('public')->put('contracts/files/a.txt', 'Hello'))
        ->toThrow(UnableToWriteFile::class);
});

it('treats transient Gel5 existence check failures as missing files', function (): void {
    Http::fake(fn (): never => throw new ConnectionException('cURL error 28: Failed to connect to files.test port 443 after 2000 ms: Timeout was reached'));

    expect(Storage::disk('public')->exists('contracts/files/missing.pdf'))->toBeFalse();
});
