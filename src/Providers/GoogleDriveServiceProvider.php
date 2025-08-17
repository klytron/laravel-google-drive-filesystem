<?php

namespace Klytron\GoogleDriveFilesystem\Providers;

use Google\Client;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;
use Klytron\GoogleDriveFilesystem\Adapters\GoogleDriveAdapter;
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

        Storage::extend('google', function ($app, $config) {
            // Validate required configuration
            if (empty($config['client_id'])) {
                throw new \InvalidArgumentException('Google Drive client_id is required');
            }
            
            if (empty($config['client_secret'])) {
                throw new \InvalidArgumentException('Google Drive client_secret is required');
            }

            $client = new Client();
            
            // Set up Google API client
            $client->setClientId($config['client_id']);
            $client->setClientSecret($config['client_secret']);
            $client->setRedirectUri($config['redirect_uri'] ?? 'http://localhost');
            $client->setAccessType('offline');
            $client->setApprovalPrompt('force');
            $client->setScopes(['https://www.googleapis.com/auth/drive']);

            // Handle authentication tokens
            if (!empty($config['refresh_token'])) {
                try {
                    // Fetch and set the access token using the refresh token
                    $accessToken = $client->fetchAccessTokenWithRefreshToken($config['refresh_token']);
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

            $adapter = new GoogleDriveAdapter($client, $folderId);
            $filesystem = new Filesystem($adapter);

            return new FilesystemAdapter($filesystem, $adapter, $config);
        });
    }
}
