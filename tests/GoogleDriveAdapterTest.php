<?php

namespace Klytron\GoogleDriveFilesystem\Tests;

use Google\Client;
use Klytron\GoogleDriveFilesystem\Adapters\GoogleDriveAdapter;
use Orchestra\Testbench\TestCase;
use ReflectionClass;

class GoogleDriveAdapterTest extends TestCase
{
    private function createAdapter(array $options = []): GoogleDriveAdapter
    {
        $client = new Client();
        return new GoogleDriveAdapter($client, 'root-folder-id', $options);
    }

    public function test_adapter_initializes_with_root_folder()
    {
        $adapter = $this->createAdapter();
        $this->assertInstanceOf(GoogleDriveAdapter::class, $adapter);
    }

    public function test_team_drive_options_applied_when_configured()
    {
        $adapter = $this->createAdapter(['team_drive' => true]);

        $reflection = new ReflectionClass($adapter);
        $property = $reflection->getProperty('defaultParameters');
        $property->setAccessible(true);

        $params = $property->getValue($adapter);
        $this->assertTrue($params['supportsAllDrives'] ?? false);
        $this->assertTrue($params['includeItemsFromAllDrives'] ?? false);
    }

    public function test_team_drive_options_disabled_by_default()
    {
        $adapter = $this->createAdapter(['team_drive' => false]);

        $reflection = new ReflectionClass($adapter);
        $property = $reflection->getProperty('defaultParameters');
        $property->setAccessible(true);

        $params = $property->getValue($adapter);
        $this->assertEmpty($params);
    }

    public function test_mime_type_resolution_for_common_extensions()
    {
        $adapter = $this->createAdapter();
        $reflection = new ReflectionClass($adapter);
        $method = $reflection->getMethod('getMimeType');
        $method->setAccessible(true);

        $this->assertEquals('image/webp', $method->invoke($adapter, 'photo.webp'));
        $this->assertEquals('image/svg+xml', $method->invoke($adapter, 'icon.svg'));
        $this->assertEquals('text/csv', $method->invoke($adapter, 'data.csv'));
        $this->assertEquals('application/sql', $method->invoke($adapter, 'dump.sql'));
        $this->assertEquals('application/gzip', $method->invoke($adapter, 'archive.gz'));
        $this->assertEquals('video/mp4', $method->invoke($adapter, 'video.mp4'));
    }

    public function test_query_string_escaping()
    {
        $adapter = $this->createAdapter();
        $reflection = new ReflectionClass($adapter);
        $method = $reflection->getMethod('escapeQueryString');
        $method->setAccessible(true);

        $escaped = $method->invoke($adapter, "O'Reilly's Book");
        $this->assertEquals("O\\'Reilly\\'s Book", $escaped);
    }
}
