<?php

namespace App\Providers;

use App\Filesystem\CloudinaryFilesystemAdapter;
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
        Storage::extend('cloudinary', function ($app, array $config): FilesystemAdapter {
            $adapter = new CloudinaryFilesystemAdapter($config);

            return new FilesystemAdapter(new Filesystem($adapter), $adapter, $config);
        });
    }
}
