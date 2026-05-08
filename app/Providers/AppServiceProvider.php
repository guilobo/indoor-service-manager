<?php

namespace App\Providers;

use App\Support\Filesystems\Gel5FilesystemAdapter;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;
use League\Flysystem\Filesystem;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Storage::extend('gel5', function (Application $app, array $config): FilesystemAdapter {
            $adapter = new Gel5FilesystemAdapter(
                endpoint: (string) ($config['endpoint'] ?? ''),
                apiKey: $config['key'] ?? null,
                root: (string) ($config['root'] ?? ''),
                publicUrl: $config['url'] ?? null,
            );

            return new FilesystemAdapter(
                new Filesystem($adapter, $config),
                $adapter,
                $config,
            );
        });
    }
}
