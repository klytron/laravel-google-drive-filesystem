<?php

namespace Klytron\GoogleDriveFilesystem\Console;

use Illuminate\Console\Command;
use Klytron\GoogleDriveFilesystem\GoogleDriveAuth;

class CheckGoogleDriveCommand extends Command
{
    protected $signature = 'google-drive:check
        {--disk=google : The filesystem disk to validate credentials for}';

    protected $description = 'Validate Google Drive credentials without side effects (no files are created, modified or deleted)';

    public function handle(): int
    {
        $disk = $this->option('disk');
        $config = config("filesystems.disks.{$disk}");

        if (!is_array($config) || ($config['driver'] ?? null) !== 'google') {
            $this->error("Disk [{$disk}] is not a configured Google Drive disk.");
            return self::FAILURE;
        }

        try {
            GoogleDriveAuth::assertCredentials($config);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        $this->info("Google Drive credentials are valid (disk [{$disk}]).");

        return self::SUCCESS;
    }
}
