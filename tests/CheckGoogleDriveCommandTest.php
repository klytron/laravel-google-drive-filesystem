<?php

namespace Klytron\GoogleDriveFilesystem\Tests;

use Klytron\GoogleDriveFilesystem\Console\CheckGoogleDriveCommand;
use Klytron\GoogleDriveFilesystem\Providers\GoogleDriveServiceProvider;
use Orchestra\Testbench\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

class CheckGoogleDriveCommandTest extends TestCase
{
    protected function getPackageProviders($app)
    {
        return [GoogleDriveServiceProvider::class];
    }

    /**
     * @return array{0: int, 1: string}
     */
    private function runCheckCommand(array $input = []): array
    {
        $command = new CheckGoogleDriveCommand();
        $command->setLaravel($this->app);

        $output = new BufferedOutput();
        $status = $command->run(new ArrayInput($input, $command->getDefinition()), $output);

        return [$status, $output->fetch()];
    }

    public function test_command_is_registered_as_google_drive_check()
    {
        $command = new CheckGoogleDriveCommand();

        $this->assertSame('google-drive:check', $command->getName());
    }

    public function test_command_rejects_non_google_disk()
    {
        config()->set('filesystems.disks.local-driver', ['driver' => 'local', 'root' => storage_path('app')]);

        [$status, $output] = $this->runCheckCommand(['--disk' => 'local-driver']);

        $this->assertSame(1, $status);
        $this->assertStringContainsString('local-driver', $output);
    }

    public function test_command_rejects_missing_disk()
    {
        [$status] = $this->runCheckCommand(['--disk' => 'does-not-exist']);

        $this->assertSame(1, $status);
    }

    public function test_command_fails_gracefully_with_invalid_credentials()
    {
        config()->set('filesystems.disks.google', [
            'driver' => 'google',
            'client_id' => 'invalid-client-id',
            'client_secret' => 'invalid-client-secret',
            'refresh_token' => 'invalid-refresh-token',
        ]);

        [$status, $output] = $this->runCheckCommand();

        $this->assertSame(1, $status);
        $this->assertStringContainsString('GOOGLE_DRIVE_REFRESH_TOKEN', $output);
    }
}
