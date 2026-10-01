<?php

namespace Klytron\GoogleDriveFilesystem\Providers;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;
use Klytron\GoogleDriveFilesystem\Adapters\GoogleDriveAdapter;
use Klytron\GoogleDriveFilesystem\Console\CheckGoogleDriveCommand;
use Klytron\GoogleDriveFilesystem\GoogleDriveAuth;
use League\Flysystem\Filesystem;

class GoogleDriveServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../../config/google-drive.php', 'filesystems.disks.google');
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../../config/google-drive.php' => config_path('google-drive.php'),
        ], 'google-drive-config');

        if ($this->app->runningInConsole()) {
            $this->commands([CheckGoogleDriveCommand::class]);
        }

        Storage::extend('google', function ($app, $config) {
            // Validate required configuration
            if (empty($config['client_id'])) {
                throw new \InvalidArgumentException('Google Drive client_id is required');
            }

            if (empty($config['client_secret'])) {
                throw new \InvalidArgumentException('Google Drive client_secret is required');
            }

            $client = GoogleDriveAuth::buildClient($config);

            // Handle authentication tokens
            if (!empty($config['refresh_token'])) {
                try {
                    // Fetch and set the access token using the refresh token
                    // (one transparent retry on transient 401/5xx failures)
                    $accessToken = GoogleDriveAuth::refreshAccessTokenWithRetry($client, $config['refresh_token']);
                    $client->setAccessToken($accessToken);
                } catch (\Exception $e) {
                    if (config('google-drive.debug', config('app.debug', false))) {
                        \Log::error('[GoogleDriveServiceProvider] Failed to refresh access token', [
                            'error' => $e->getMessage()
                        ]);
                    }
                    throw new \RuntimeException('Failed to authenticate with Google Drive: ' . $e->getMessage(), 0, $e);
                }
            } elseif (!empty($config['access_token'])) {
                // Set the access token directly if provided
                $client->setAccessToken($config['access_token']);
            } else {
                throw new \InvalidArgumentException('Either access_token or refresh_token is required for Google Drive authentication');
            }

            // Get folder ID from config or env
            $folderId = $config['folder_id'] ?? env('GOOGLE_DRIVE_FOLDER_ID');

            $adapter = new GoogleDriveAdapter($client, $folderId, $config);
            $filesystem = new Filesystem($adapter);

            return new FilesystemAdapter($filesystem, $adapter, $config);
        });
    }
}
